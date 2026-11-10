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
     * Resolve verified Sketchfab 3D vehicle UID based on make/model.
     * Returns null if no verified accurate 3D model exists, so the system shifts to 2D blueprint.
     */
    public function getSketchfabUidAttribute(): ?string
    {
        // 1. If user set an explicit custom 3D model UID, use it
        if (! empty($this->attributes['sketchfab_uid'])) {
            return $this->attributes['sketchfab_uid'];
        }

        $make = strtolower(trim($this->make ?? ''));
        $model = strtolower(trim($this->model ?? ''));
        $combined = "{$make} {$model}";

        // 2. Strict, verified accurate 3D model mappings ONLY
        if (str_contains($combined, 'corolla')) {
            return '36f95efb0585464cae43a25a3b3392e8'; // 2014 Toyota Corolla EU Sedan
        }
        if (str_contains($combined, 'civic')) {
            return 'd5ca29a5d2f341b691118ffb5560b19b'; // 2022 Honda Civic Sedan
        }
        if (str_contains($combined, 'camry')) {
            return '53f8cbf2483940fba956f4e740a3a4ae'; // 2025 Toyota Camry Hybrid Sedan
        }
        if (str_contains($combined, 'supra')) {
            return 'b7616ec43ecf4ffd8ed810d94f15eea6'; // Toyota Supra Coupe
        }
        if (str_contains($combined, 'model 3')) {
            return '5ef9b845aaf44203b6d04e2c677e444f'; // Tesla Model 3 Sedan
        }
        if (str_contains($combined, 'model y')) {
            return 'c0a86cac582d4b33aba0fb1b1912d970'; // Tesla Model Y Crossover
        }
        if (str_contains($combined, 'elantra')) {
            return '4ba1b1b0eb844e318cc708ada1f2f51f'; // 2024 Hyundai Elantra N Sedan
        }
        if (str_contains($combined, 'mustang')) {
            return '5f4e3965f79540a9888b5d05acea5943'; // Ford Mustang
        }

        // 3. No generic guessing! If no verified 3D asset exists, return NULL.
        // The interface will shift to 2D Blueprint with sensor pins.
        return null;
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
