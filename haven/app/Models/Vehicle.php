<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nickname',
        'make',
        'model',
        'body_type',
        'year',
        'plate_number',
        'color',
        'image_path',
        'engine_type',
        'transmission',
        'mileage_km',
        'notes',
        'sketchfab_uid',
        'device_key',
        'alert_thresholds',
    ];

    public static function generateDeviceKey(): string
    {
        return 'hvn_dev_'.bin2hex(random_bytes(16));
    }

    protected static function booted(): void
    {
        static::creating(function (Vehicle $vehicle) {
            if (empty($vehicle->device_key)) {
                $vehicle->device_key = static::generateDeviceKey();
            }
        });

        static::deleting(function (Vehicle $vehicle) {
            if (! empty($vehicle->image_path)) {
                Storage::disk('public')->delete($vehicle->image_path);
            }
            foreach ($vehicle->diagnostics as $diagnostic) {
                $diagnostic->deleteFiles();
            }
        });
    }

    protected $casts = [
        'year' => 'integer',
        'mileage_km' => 'integer',
        'alert_thresholds' => 'array',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function diagnostics(): HasMany
    {
        return $this->hasMany(Diagnostic::class);
    }

    // Accessor — display name
    public function getDisplayNameAttribute(): string
    {
        return $this->nickname ?: "{$this->year} {$this->make} {$this->model}";
    }

    /**
     * Resolve inferred or explicit body type (sedan, suv, coupe, hatchback, pickup).
     */
    public function getResolvedBodyTypeAttribute(): string
    {
        if (! empty($this->body_type)) {
            return strtolower($this->body_type);
        }

        $combined = strtolower(($this->make ?? '').' '.($this->model ?? ''));
        if (preg_match('/\b(suv|crossover|rav4|cr-v|tucson|sportage|cx-5|cx-9|x3|x5|q5|q7|urus|macan|cayenne|montero|fortuner|everest|terra|mu-x|explorer|highlander|trailblazer)\b/i', $combined)) {
            return 'suv';
        }
        if (preg_match('/\b(coupe|mustang|supra|gt-r|911|cayman|boxster|miata|brz|gr86|corvette|camaro|challenger|ferrari|lamborghini|r8)\b/i', $combined)) {
            return 'coupe';
        }
        if (preg_match('/\b(hatch|hatchback|yaris|fit|jazz|golf|polo|swift|fiesta|focus|wigo|brio)\b/i', $combined)) {
            return 'hatchback';
        }
        if (preg_match('/\b(pickup|truck|hilux|ranger|d-max|navara|strada|f-150|silverado|tacoma|colorado)\b/i', $combined)) {
            return 'pickup';
        }

        return 'sedan';
    }

    /**
     * Resolve public URL for uploaded vehicle photograph or fallback to realistic 3D studio render.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (! empty($this->image_path)) {
            return Storage::disk('public')->url($this->image_path);
        }

        $type = $this->resolved_body_type;
        $defaultFile = "images/vehicles/{$type}.jpg";
        if (file_exists(public_path($defaultFile))) {
            return asset($defaultFile);
        }

        return null;
    }

    /**
     * Body type formatted label.
     */
    public function getBodyTypeLabelAttribute(): string
    {
        return match (strtolower($this->body_type ?? 'sedan')) {
            'suv' => 'SUV / Crossover',
            'hatchback' => 'Hatchback',
            'coupe' => 'Coupe / Sport',
            'pickup' => 'Pickup / Truck',
            default => 'Sedan',
        };
    }

    /**
     * Accent lighting color based on registered paint color or default cyan.
     */
    public function getAccentColorHexAttribute(): string
    {
        $c = strtolower(trim($this->color ?? ''));
        if (empty($c)) {
            return '#38bdf8'; // HAVEN Cyan
        }
        if (preg_match('/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $c)) {
            return $c;
        }
        if (str_contains($c, 'red') || str_contains($c, 'crimson') || str_contains($c, 'maroon')) {
            return '#ef4444';
        }
        if (str_contains($c, 'blue') || str_contains($c, 'navy') || str_contains($c, 'sapphire')) {
            return '#38bdf8';
        }
        if (str_contains($c, 'yellow') || str_contains($c, 'gold')) {
            return '#eab308';
        }
        if (str_contains($c, 'orange') || str_contains($c, 'bronze') || str_contains($c, 'copper')) {
            return '#f97316';
        }
        if (str_contains($c, 'green') || str_contains($c, 'lime') || str_contains($c, 'emerald')) {
            return '#10b981';
        }
        if (str_contains($c, 'black') || str_contains($c, 'dark') || str_contains($c, 'obsidian')) {
            return '#64748b';
        }
        if (str_contains($c, 'white') || str_contains($c, 'pearl')) {
            return '#e2e8f0';
        }
        if (str_contains($c, 'silver') || str_contains($c, 'gray') || str_contains($c, 'grey')) {
            return '#94a3b8';
        }
        if (str_contains($c, 'purple') || str_contains($c, 'violet')) {
            return '#a855f7';
        }

        return '#38bdf8';
    }

    /**
     * Verified Sketchfab 3D Digital Twin catalog organized by Make -> Model -> UID.
     */
    public const SKETCHFAB_CATALOG = [
        'Toyota' => [
            'Vios' => '0899fcfeac9845e09ae296145c7b2b67',
            'Corolla' => '36f95efb0585464cae43a25a3b3392e8',
            'Camry' => '53f8cbf2483940fba956f4e740a3a4ae',
            'Supra' => 'b7616ec43ecf4ffd8ed810d94f15eea6',
            'Fortuner' => 'a70b997f11b7482e9affbf318a508f85',
            'Hilux' => '82bd37c5065040098fb0b86e07fcb959',
            'Innova' => 'c8e1bbc9292b4e198dba839c32f02fa9',
            'Wigo' => '6aaf7c79cb7341c6b3189dd39dc15f9a',
            'RAV4' => 'ed155ad0cb7d447085a519eaff9aa2df',
            'Avanza' => '711adccbd846427b9dbca71e27fee50b',
            'Raize' => 'd08c1b39cbec4eb192190a86721e25de',
            'Land Cruiser' => '91b5815c64eb43b0a88f6fdb9df774e4',
            'Yaris' => '1c2ce5ff548f4ab49d72183d1e0f2afe',
            'Prius' => '04eaf6b288cc466785365b7d985e3b29',
            '86' => 'a5737bf3cc9b4179a6e5ebe173ff70d9',
            'Hiace' => '545c3ca69d654487979f63228653b590',
        ],
        'Honda' => [
            'Civic' => 'd5ca29a5d2f341b691118ffb5560b19b',
            'City' => '75671276d4da476294202a5dc050a99e',
            'CR-V' => '4d0751311d76473b81377f5bd2da273b',
            'HR-V' => 'bf835b5a5e6f4b79a134db5156992cf0',
            'Brio' => '922c636df4b34c539e6b8cefabc38cbb',
            'Jazz' => '2b1f535f185e4a6abf6b0a3222487704',
            'Fit' => '2b1f535f185e4a6abf6b0a3222487704',
            'Accord' => 'c289d72487b44f7bbdc6fa070e3b58d6',
            'NSX' => '1cc15628a00a4739a6b6c01128927c8d',
            'S2000' => '1daea6b886c34945bbb54c74dd6ef362',
        ],
        'Mitsubishi' => [
            'L300' => '74bcb23004324c8392fdc8b3ec9f9e5b',
            'Montero Sport' => '6b4baffaa03444d19cfc8c58a497d75b',
            'Xpander' => 'b74c5df4815b45afbf550330ba10ff5b',
            'Mirage' => 'd8e74e359c874e1cae7c93ff9609c23b',
            'Strada' => '8bc8a053ac6e468384a2db38cd7703a9',
            'Triton' => '8bc8a053ac6e468384a2db38cd7703a9',
            'Lancer' => '557eb82f542743e3bf0d55dcea564547',
            'Pajero' => 'bec38f586d9349ee9c1f295b6f5569cb',
            'Outlander' => '45be773263054877bf128e86c06e9af7',
            'Eclipse' => '1e6f888ba83e4d6a93c747013caece7e',
        ],
        'Nissan' => [
            'Navara' => '2a877fdf866b4dbb8b0aa4dd9ef2319d',
            'Terra' => '46727bb6f4f84df5878bed50c58a70bf',
            'Almera' => '18af87c9490e4acb80a46b70ca8d86ed',
            'Versa' => '18af87c9490e4acb80a46b70ca8d86ed',
            'GT-R' => '8810e677eca543a6b840cf67dd064aa4',
            'Sentra' => '4e261bd2d93a438ebdabf8cc6b41cf58',
            'Sylphy' => '4e261bd2d93a438ebdabf8cc6b41cf58',
            'Patrol' => '80162fe6fadd4650a2a6c38fca3662a0',
            'Urvan' => '580ec8decbb540b8a73369af5973f15a',
            '370Z' => 'c0b36c1dc2b345418c3c190c5be7a359',
            'Silvia' => '151053ad7e0a4fcf944a7b523722dad6',
            'Skyline' => '7b142ea3376e4811a326256c59bbc7a2',
        ],
        'Ford' => [
            'Ranger' => 'e93ac097ee9a46a4a9decca9aceed03c',
            'Everest' => 'c37bec0353a94f90a3acbe09ceb3aecf',
            'Mustang' => '5f4e3965f79540a9888b5d05acea5943',
            'Territory' => '4ffa712ce0af46bf9e7fab4fc7eb0702',
            'Explorer' => 'f1318e0523cd428c87f6653dd5cded85',
            'F-150' => '5bde9684bf6d40e0902ee8e482ad1063',
            'Focus' => '9bc5e9e5edd5487f97d8501a22c3c8b5',
            'Fiesta' => '1baa5c512e3c4ac3af4131fce9557ebe',
            'Bronco' => '98b8590146a8447da52a1fefce064f0b',
        ],
        'Hyundai' => [
            'Elantra' => '4ba1b1b0eb844e318cc708ada1f2f51f',
            'Tucson' => '7674a3bd168a451daf73fc0a33d39589',
            'Accent' => 'cec6b04b06724f1088129c1054ccb1ec',
            'Stargazer' => '8ee016bf8d534fa48f82ac242528a2a3',
            'Santa Fe' => '0b21aa4f375249ff9fd421a0e41aa55e',
            'Creta' => 'fa88bbdbe0df406b8eab167b9591d2f2',
            'Sonata' => 'dfcb71c2d0a44b888d8f116d0d8ef169',
            'Ioniq 5' => '675e78311e8440d88714bd212cb7a8fb',
            'Veloster' => 'd85ea2322d814899ad5905210f2c0e5a',
        ],
        'Kia' => [
            'Soluto' => '584472f92f0f4439b410d93c2b0d1ec5',
            'Stonic' => '43ac15c68e934eaf8785d74816000bdc',
            'Sportage' => 'c55735b0036d4c2d998bb8f9414a50c6',
            'Sorento' => '340ded1d34c149a095b3281a1232494a',
            'Picanto' => '8727b8185a8a4a5f963802984577a5fe',
            'Carnival' => 'ab0623b403b3471b844c518de9c3ee3b',
            'Seltos' => '8a5862040602494d8ab94e21251c5d6b',
            'Stinger' => 'b1493e68069f4b869743de7a4220a4f4',
            'EV6' => '776181e22d694282b90c50969298b84c',
        ],
        'Suzuki' => [
            'Ertiga' => '4f14afac3ac44ec4a2fb153e18452f8b',
            'Jimny' => '89132b2c7dbc435db36fb0385786f150',
            'Swift' => '218c3c0c6afd4d0eb343bd50d8868fd0',
            'Dzire' => '95451c00cb2d48778f67d798167e7237',
            'Celerio' => '9a399ea7792d4fd28c2b34d525ff91b1',
            'S-Presso' => 'eee53feaf00741f0a5e5535cad0dcae4',
            'Vitara' => '03e3db7b0d4a42d5a52f6740a3d5657d',
            'Carry' => '5c0007c0c6ed4d298b8c80646efa09d2',
        ],
        'Mazda' => [
            'Mazda3' => 'a72de3f3c1604409a7e6fc6be9854c9d',
            'Mazda 3' => 'a72de3f3c1604409a7e6fc6be9854c9d',
            'CX-5' => '0ca0354c7b2a41bbb84c835ff046e8e5',
            'MX-5' => 'f0a9612662574b6db712c408da8bffb7',
            'Miata' => 'f0a9612662574b6db712c408da8bffb7',
            'Mazda2' => 'e88b9a72d8ab40f7979cbc6a9eae0465',
            'Mazda 2' => 'e88b9a72d8ab40f7979cbc6a9eae0465',
            'CX-30' => '79e572aae24b4ff0b1b0c1e3dfddf52b',
            'CX-9' => '314cf4e76e0c4989b6182bebae9a3b42',
            'RX-7' => '45cb02e634ed477a9a04bb19813443f2',
            'RX-8' => 'e8268b3c50e14d85b9e0570ffa4f2bb3',
        ],
        'Subaru' => [
            'Outback' => '6d1badf2a3124ebabcfe08077474739e',
            'Forester' => '24c934fdef954cb189930cf809ef7b01',
            'WRX' => 'a09de8983d554173b9eecd48ba7e678f',
            'BRZ' => '8fecd93279644ada9111c5bfa2226327',
            'XV' => 'b6a5342f0de04d84b96376e243fd8183',
            'Crosstrek' => 'b6a5342f0de04d84b96376e243fd8183',
            'Impreza' => '7fb4298d5d8f4185b25bb2c43d7f3787',
            'Legacy' => 'c0ffde08f5e04cca9dffb449a5eef759',
        ],
        'Chevrolet' => [
            'Camaro' => 'e9c677d1c6a3454ebb4746a160129386',
            'Corvette' => '2b509d1bce104224b147c81757f6f43a',
            'Trailblazer' => '0ce84c24b64f4758989b5efa7fa20d94',
            'Tracker' => '43e7378889b34e9cabf23e6f386d4b87',
            'Tahoe' => 'a11d407e141b4a88b2a424060075e357',
            'Suburban' => '44bdb8317f0840b3bc858e606f86db6b',
            'Silverado' => 'd4acf44cf9ea43f086038c9cd0e3bd0d',
        ],
        'Isuzu' => [
            'D-Max' => '5b4443c246b34b79bb2504c4c4a87f1a',
            'mu-X' => '58956e0c15b246c391ba478fe8489a19',
        ],
        'Volkswagen' => [
            'Golf' => '1fc46cb37bd748e3bb9355fcedaf3817',
            'Polo' => 'bab77902c638427bb85e68b6762a481f',
            'Tiguan' => '0703b26f5c6446dabf1ab3caecce586f',
            'Beetle' => '969a477451ee40bb8e715ec0907d187e',
            'Jetta' => '7805392209c44fffa6b4decda5510e69',
            'Passat' => 'c839fbd24faa4477b9fb7b3fc6f68fa4',
        ],
        'BMW' => [
            '3 Series' => 'ac3c7013434e403e8faff87948caf422',
            'M3' => 'ac3c7013434e403e8faff87948caf422',
            'M4' => '8e87379f40fd40dcac0a751e22c1a188',
            'M5' => '10a95624ce5046f5805bd9d17256e033',
            'X5' => '9b211d525797457e988c903f67d0b753',
            'i8' => '4b143b95aec045bf8912d99662f8d580',
            'Z4' => 'c6178ca22425467080939e29e1b42f8c',
        ],
        'Mercedes-Benz' => [
            'C-Class' => '21393b5f402543848db2f26f95a2e9fa',
            'E-Class' => '820387a848a8449eb6565ff35e74388a',
            'S-Class' => '6b1c0ad55406406a8cf8177dc0a4f2b9',
            'G-Class' => '52296f1a65d54a85a2ed7cb67604e554',
            'AMG GT' => 'fbff228fb1a94d34b808c57264ab7103',
            'GLE' => '913b898d94a343e9981500103aa5b9bb',
        ],
        'Audi' => [
            'A4' => 'fd2e6813f8224d58b104dfbfc1ab3173',
            'R8' => 'e17e438f076f4427a58d93aa779edaed',
            'RS6' => '980dbda2cbbb4bae8decaed2fa80aa0c',
            'Q5' => '60fea790b21c48bcb379be2a6d2c1f81',
            'Q7' => '722e900559cf41bdbf9acb8df606b3b8',
            'TT' => '1efef0b0f1794a72ae78726f2f40747f',
        ],
        'Lexus' => [
            'IS' => '281a547f226543d680e4f27ae0304278',
            'RX' => '00365979e4d54e559c3dbff392904924',
            'LX' => '1dd2df7c68e74ca3a3ea83fb6c4bd1a2',
            'LC 500' => '3f6118da005a457a9ce6d737e5d1e37d',
            'LFA' => '83d062d1cd144e6a944c7415ef864889',
        ],
        'Geely' => [
            'Coolray' => '4200138ec0b640b98defde1481bfe165',
            'Okavango' => '61561d1133cc46d8b0c7a4a1277038a8',
            'Emgrand' => '12c726e06d4b41fda2850c218c2a3d3d',
        ],
        'MG' => [
            'ZS' => '0e89cca3d64141ac92d1d2d17806c128',
            'GT' => '976440e7a6b84123a6398a621430799e',
        ],
        'BYD' => [
            'Atto 3' => 'f7ef899186d445d48e4781ddfc84fddc',
            'Dolphin' => 'e8f3cf54c0e545b1b290d7a55ca77fca',
            'Seal' => '2e82d2aaaa3d47debf39b1160d3fe376',
            'Han' => '262fad42369a4a9c8c3371b7dfbc922f',
        ],
        'Peugeot' => [
            '3008' => '4f7c7318a29d4e16a6047c2d58bba28d',
            '5008' => '7aa26f63a1b641f889a6e03a8f557a82',
            '2008' => '6291b66452614464889a71c8337b8c85',
            '208' => '3d4a1578573645de9a2915e93b7edcf7',
        ],
        'Volvo' => [
            'XC90' => '19303465af074d07abab50fea3902fdd',
            'XC60' => 'eb8ce77c64c04339983cdf30593abca3',
            'XC40' => 'affc79b0ef594f5ebaba3a8d1c4e9b1e',
            'S90' => '9462b07c10244fd4a28d86846dc9e3a9',
        ],
        'Tesla' => [
            'Model 3' => '5ef9b845aaf44203b6d04e2c677e444f',
            'Model Y' => 'c0a86cac582d4b33aba0fb1b1912d970',
        ],
    ];

    /**
     * Return catalog models with verified 3D twins for a given Make.
     *
     * @return list<string>
     */
    public static function catalogModelsForMake(string $make): array
    {
        $makeClean = strtolower(trim($make));
        foreach (self::SKETCHFAB_CATALOG as $catMake => $models) {
            if (strtolower($catMake) === $makeClean) {
                return array_keys($models);
            }
        }

        return [];
    }

    /**
     * Resolve verified Sketchfab 3D vehicle UID for any make/model pair.
     */
    public static function resolveSketchfabUid(?string $make, ?string $model): ?string
    {
        $makeClean = strtolower(trim($make ?? ''));
        $modelClean = strtolower(trim($model ?? ''));
        if ($modelClean === '') {
            return null;
        }

        $normModel = str_replace(['-', ' '], '', $modelClean);

        // 1. Check matching make first
        foreach (self::SKETCHFAB_CATALOG as $catMake => $models) {
            if ($makeClean === '' || strtolower($catMake) === $makeClean || str_contains($makeClean, strtolower($catMake))) {
                foreach ($models as $catModel => $uid) {
                    $cmLower = strtolower($catModel);
                    $cmNorm = str_replace(['-', ' '], '', $cmLower);
                    if ($modelClean === $cmLower || $normModel === $cmNorm || str_contains($modelClean, $cmLower) || ($cmNorm !== '' && str_contains($normModel, $cmNorm))) {
                        return $uid;
                    }
                }
            }
        }

        // 2. Fallback: check model name across all makes if unique/unambiguous
        foreach (self::SKETCHFAB_CATALOG as $models) {
            foreach ($models as $catModel => $uid) {
                $cmLower = strtolower($catModel);
                $cmNorm = str_replace(['-', ' '], '', $cmLower);
                if (strlen($cmNorm) >= 3 && ($modelClean === $cmLower || $normModel === $cmNorm || str_contains($modelClean, $cmLower))) {
                    return $uid;
                }
            }
        }

        return null;
    }

    /**
     * Resolve verified Sketchfab 3D vehicle UID based on make/model.
     * Returns null if no verified accurate 3D model exists, so the system shifts to 2D blueprint.
     */
    public function getSketchfabUidAttribute(): ?string
    {
        // 1. If user set an explicit custom 3D model UID, use it
        if (! empty($this->attributes['sketchfab_uid'])) {
            return $this->attributes['sketchfab_uid'];
        }

        return self::resolveSketchfabUid($this->make, $this->model);
    }

    /**
     * Check if a verified 3D model is available for this vehicle.
     */
    public function has3dModel(): bool
    {
        return ! empty($this->sketchfab_uid);
    }

    public function getSketchfabModelNameAttribute(): string
    {
        return "{$this->year} {$this->make} {$this->model}";
    }

    /**
     * Check whether vehicle meets UMak CET Capstone Scope:
     * Model Year 2015+ and Spark-Ignition Gasoline ICE.
     */
    public function isScopeCompliant(): bool
    {
        return ($this->year >= 2015) && ! $this->isExcludedPowertrain();
    }

    public function isExcludedPowertrain(): bool
    {
        $engine = strtolower($this->engine_type ?? '');

        return (bool) preg_match('/\b(diesel|tdi|crdi|cummins|duramax|hybrid|phev|ev|electric|motorcycle)\b/i', $engine);
    }

    public function getScopeMetaAttribute(): array
    {
        $compliant = $this->isScopeCompliant();
        if ($compliant) {
            return [
                'compliant' => true,
                'label' => '2015+ Gasoline (In Scope)',
                'badge_css' => 'h-badge-teal',
                'color' => 'var(--h-teal)',
                'icon' => 'check-circle',
                'notice' => null,
            ];
        }

        $reasons = [];
        if ($this->year < 2015) {
            $reasons[] = "Model year {$this->year} is pre-2015";
        }
        if ($this->isExcludedPowertrain()) {
            $reasons[] = "Powertrain ({$this->engine_type}) is non-gasoline";
        }

        return [
            'compliant' => false,
            'label' => 'Out of Scope',
            'badge_css' => 'h-badge-amber',
            'color' => 'var(--h-amber)',
            'icon' => 'alert-triangle',
            'notice' => implode(', ', $reasons).'. Note: Research scope is 2015+ spark-ignition gasoline passenger vehicles.',
        ];
    }
}
