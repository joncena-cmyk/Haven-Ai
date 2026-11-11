<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Client for the public NHTSA vPIC catalog (no API key).
 * Used to fill make / model / year / engine when registering a vehicle.
 */
class NhtsaVehicleService
{
    private string $baseUrl;

    private int $timeoutSec;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.nhtsa.url', 'https://vpic.nhtsa.dot.gov/api/vehicles'), '/');
        $this->timeoutSec = (int) config('services.nhtsa.timeout', 20);
    }

    /**
     * Unique make names for passenger cars, trucks, and MPVs.
     *
     * @return list<string>
     */
    public function makes(): array
    {
        return Cache::remember('nhtsa.makes.v1', now()->addHours(24), function () {
            $names = [];

            foreach (['car', 'truck', 'mpv'] as $type) {
                try {
                    $rows = $this->results("GetMakesForVehicleType/{$type}");
                } catch (RuntimeException) {
                    continue;
                }

                foreach ($rows as $row) {
                    $name = $this->clean($row['MakeName'] ?? '');
                    if ($name !== '') {
                        // Filter out non-passenger commercial equipment & coachbuilders
                        if (preg_match('/\b(sweeper|trailer|fabricat|equipment|lafrance|coach|ambulance|fire|crane|tanker|autocar|heavy|chassis|utility|semi)\b/i', $name)) {
                            continue;
                        }
                        $names[mb_strtoupper($name)] = $this->titleCase($name);
                    }
                }
            }

            if ($names === []) {
                throw new RuntimeException('Vehicle catalog is temporarily unavailable.');
            }

            $list = array_values($names);
            natcasesort($list);

            return array_values($list);
        });
    }

    /**
     * Model names for a make, optionally filtered by model year.
     *
     * @return list<string>
     */
    public function models(string $make, ?int $year = null): array
    {
        $make = $this->clean($make);
        if ($make === '') {
            return [];
        }

        $cacheKey = 'nhtsa.models.v2.'.md5(mb_strtolower($make).'|'.($year ?: 'any'));

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($make, $year) {
            $names = [];

            // 1. Seed with HAVEN's verified 3D Digital Twin & PH/Asian catalog models for this Make
            foreach (\App\Models\Vehicle::catalogModelsForMake($make) as $verifiedModel) {
                $names[mb_strtoupper($verifiedModel)] = $verifiedModel;
            }

            // 2. Merge with NHTSA vPIC models when reachable
            $path = $year
                ? 'GetModelsForMakeYear/make/'.$this->pathSegment($make).'/modelyear/'.$year
                : 'GetModelsForMake/'.$this->pathSegment($make);

            try {
                foreach ($this->results($path) as $row) {
                    $name = $this->clean($row['Model_Name'] ?? $row['Model'] ?? '');
                    if ($name !== '') {
                        $names[mb_strtoupper($name)] = $name;
                    }
                }
            } catch (RuntimeException $e) {
                if ($names === []) {
                    throw $e;
                }
            }

            $list = array_values($names);
            natcasesort($list);

            return array_values($list);
        });
    }

    /**
     * Decode a VIN into HAVEN vehicle fields.
     *
     * @return array{vin:string,make:?string,model:?string,year:?int,engine_type:?string,transmission:?string}
     */
    public function decodeVin(string $vin): array
    {
        $vin = strtoupper(preg_replace('/[^A-HJ-NPR-Z0-9]/i', '', $vin) ?? '');

        if (strlen($vin) !== 17) {
            throw new InvalidArgumentException('VIN must be 17 characters (letters I, O, and Q are not used).');
        }

        $row = $this->results('DecodeVinValues/'.$vin)[0] ?? [];

        if ($row === []) {
            throw new RuntimeException('VIN lookup returned no data.');
        }

        $errorCode = (string) ($row['ErrorCode'] ?? '');
        $make = $this->usable($row['Make'] ?? '');
        $model = $this->usable($row['Model'] ?? '');
        $yearRaw = $this->usable($row['ModelYear'] ?? '');

        if ($make === null && ($errorCode === '' || ! str_starts_with($errorCode, '0'))) {
            $message = $this->usable($row['ErrorText'] ?? '') ?? 'This VIN could not be decoded.';
            throw new InvalidArgumentException($message);
        }

        return [
            'vin' => $vin,
            'make' => $make ? $this->titleCase($make) : null,
            'model' => $model,
            'year' => $yearRaw && ctype_digit($yearRaw) ? (int) $yearRaw : null,
            'engine_type' => $this->composeEngine($row),
            'transmission' => $this->mapTransmission(
                $this->usable($row['TransmissionStyle'] ?? '')
                ?? $this->usable($row['TransmissionDescriptor'] ?? '')
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function results(string $path): array
    {
        try {
            $response = Http::timeout($this->timeoutSec)
                ->acceptJson()
                ->get("{$this->baseUrl}/{$path}", ['format' => 'json']);

            if (! $response->successful()) {
                Log::warning("NHTSA vPIC returned {$response->status()} for {$path}");
                throw new RuntimeException('Vehicle catalog is temporarily unavailable.');
            }

            $results = $response->json('Results');

            return is_array($results) ? $results : [];
        } catch (ConnectionException $e) {
            Log::warning("NHTSA vPIC request failed for {$path}: {$e->getMessage()}");
            throw new RuntimeException('Vehicle catalog is temporarily unavailable.');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function composeEngine(array $row): ?string
    {
        $model = $this->usable($row['EngineModel'] ?? '');
        if ($model) {
            return $model;
        }

        $parts = [];
        $displacement = $this->usable($row['DisplacementL'] ?? '');
        if ($displacement) {
            $parts[] = rtrim(rtrim($displacement, '0'), '.').'L';
        }

        $cylinders = $this->usable($row['EngineCylinders'] ?? '');
        if ($cylinders) {
            $parts[] = $cylinders.'-cyl';
        }

        $config = $this->usable($row['EngineConfiguration'] ?? '');
        if ($config && count($parts) < 2) {
            $parts[] = $config;
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    private function mapTransmission(?string $style): ?string
    {
        if ($style === null) {
            return null;
        }

        $value = strtolower($style);

        if (str_contains($value, 'cvt') || str_contains($value, 'continuously')) {
            return 'CVT';
        }
        if (str_contains($value, 'manual')) {
            return 'Manual';
        }
        if (str_contains($value, 'automatic') || str_contains($value, 'auto')) {
            return 'Automatic';
        }

        return 'Other';
    }

    private function usable(mixed $value): ?string
    {
        $value = $this->clean((string) $value);

        if ($value === '' || strcasecmp($value, 'Not Applicable') === 0 || strcasecmp($value, 'N/A') === 0) {
            return null;
        }

        return $value;
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function titleCase(string $value): string
    {
        $titled = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');

        return preg_replace(
            ['/\bBmw\b/', '/\bGmc\b/', '/\bKia\b/'],
            ['BMW', 'GMC', 'Kia'],
            $titled
        ) ?? $titled;
    }

    private function pathSegment(string $value): string
    {
        return rawurlencode($value);
    }
}
