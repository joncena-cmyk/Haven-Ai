<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nickname',
        'make',
        'model',
        'year',
        'plate_number',
        'color',
        'engine_type',
        'transmission',
        'mileage_km',
        'notes',
        'device_key',
        'alert_thresholds',
    ];

    protected static function booted(): void
    {
        static::creating(function (Vehicle $vehicle) {
            if (empty($vehicle->device_key)) {
                $vehicle->device_key = 'hvn_dev_' . bin2hex(random_bytes(16));
            }
        });
    }

    protected $casts = [
        'year'             => 'integer',
        'mileage_km'       => 'integer',
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
     * Check whether vehicle meets UMak CET Capstone Scope:
     * Model Year 2015+ and Spark-Ignition Gasoline ICE.
     */
    public function isScopeCompliant(): bool
    {
        return ($this->year >= 2015) && !$this->isExcludedPowertrain();
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
                'label'     => '2015+ Gasoline (In Scope)',
                'badge_css' => 'h-badge-teal',
                'color'     => 'var(--h-teal)',
                'icon'      => 'check-circle',
                'notice'    => null,
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
            'label'     => 'Out of Scope',
            'badge_css' => 'h-badge-amber',
            'color'     => 'var(--h-amber)',
            'icon'      => 'alert-triangle',
            'notice'    => implode(', ', $reasons) . '. Note: Research scope is 2015+ spark-ignition gasoline passenger vehicles.',
        ];
    }
}
