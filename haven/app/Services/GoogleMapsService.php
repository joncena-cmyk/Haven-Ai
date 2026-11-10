<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Maps Platform (Places API New) — finds nearby auto-repair shops.
 * The API key stays server-side; the browser only talks to HAVEN.
 */
class GoogleMapsService
{
    public function isConfigured(): bool
    {
        return filled(config('services.google_maps.key'));
    }

    /**
     * @return array{ok:bool, shops:array<int,array<string,mixed>>, error:?string}
     */
    public function nearbyRepairShops(float $lat, float $lng, int $max = 8): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'shops' => [], 'error' => 'not_configured'];
        }

        try {
            $res = Http::timeout(10)
                ->withHeaders([
                    'X-Goog-Api-Key' => config('services.google_maps.key'),
                    'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.location,places.rating,places.userRatingCount,places.currentOpeningHours.openNow,places.nationalPhoneNumber,places.googleMapsUri',
                ])
                ->post('https://places.googleapis.com/v1/places:searchNearby', [
                    'includedTypes' => ['car_repair'],
                    'maxResultCount' => max(1, min($max, 20)),
                    'rankPreference' => 'DISTANCE',
                    'locationRestriction' => [
                        'circle' => [
                            'center' => ['latitude' => $lat, 'longitude' => $lng],
                            'radius' => (float) max(500, min((int) config('services.google_maps.radius_m', 10000), 50000)),
                        ],
                    ],
                ]);

            if (! $res->successful()) {
                Log::warning('Google Places error', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 500)]);

                return ['ok' => false, 'shops' => [], 'error' => 'upstream_'.$res->status()];
            }

            $shops = collect($res->json('places', []))->map(function (array $p) use ($lat, $lng) {
                $pl = $p['location']['latitude'] ?? null;
                $pg = $p['location']['longitude'] ?? null;

                return [
                    'id' => $p['id'] ?? null,
                    'name' => $p['displayName']['text'] ?? 'Auto repair shop',
                    'address' => $p['formattedAddress'] ?? null,
                    'rating' => $p['rating'] ?? null,
                    'rating_count' => $p['userRatingCount'] ?? null,
                    'open_now' => $p['currentOpeningHours']['openNow'] ?? null,
                    'phone' => $p['nationalPhoneNumber'] ?? null,
                    'maps_url' => $p['googleMapsUri'] ?? null,
                    'lat' => $pl,
                    'lng' => $pg,
                    'distance_km' => ($pl !== null && $pg !== null) ? round($this->haversineKm($lat, $lng, $pl, $pg), 1) : null,
                ];
            })->values()->all();

            return ['ok' => true, 'shops' => $shops, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('Google Places exception: '.$e->getMessage());

            return ['ok' => false, 'shops' => [], 'error' => 'unavailable'];
        }
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

