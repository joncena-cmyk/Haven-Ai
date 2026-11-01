<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diagnostic extends Model
{
    protected $fillable = [
        'user_id',
        'vehicle_id',
        'audio_path',
        'audio_duration_sec',
        'audio_original_name',
        'vibration_path',
        'vibration_sample_count',
        'vibration_peak_g',
        'vibration_rms_g',
        'temperature_c',
        'rpm',
        'voltage_v',
        'current_ma',
        'sensor_data',
        'status',
        'recording_mode',
        'notes',
        'ml_condition',
        'ml_confidence',
        'ml_result_raw',
        'ml_processed_at',
    ];

    protected $casts = [
        'ml_result_raw'    => 'array',
        'ml_processed_at'  => 'datetime',
        'vibration_peak_g' => 'float',
        'vibration_rms_g'  => 'float',
        'temperature_c'    => 'float',
        'rpm'              => 'integer',
        'voltage_v'        => 'float',
        'current_ma'       => 'float',
        'sensor_data'      => 'array',
        'ml_confidence'    => 'float',
    ];

    public function hasMultiSensor(): bool
    {
        return !is_null($this->temperature_c) || !is_null($this->rpm) || !is_null($this->voltage_v) || !empty($this->sensor_data);
    }

    public function getTemperatureFAttribute(): ?float
    {
        return !is_null($this->temperature_c) ? round(($this->temperature_c * 9/5) + 32, 1) : null;
    }

    public function getRecordingModeLabelAttribute(): string
    {
        return match ($this->recording_mode) {
            'revving' => 'Baseline Revving (2,000–2,500 RPM)',
            default   => 'Baseline Idle (750–900 RPM)',
        };
    }

    /* ── Relationships ── */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /* ── Accessors ── */

    /**
     * Human-readable status label with colour key.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'      => 'Draft',
            'submitted'  => 'Submitted',
            'processing' => 'Processing',
            'completed'  => 'Completed',
            'failed'     => 'Failed',
            default      => ucfirst($this->status),
        };
    }

    /**
     * CSS badge class for this status.
     */
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'draft'      => 'h-badge-gray',
            'submitted'  => 'h-badge-teal',
            'processing' => 'h-badge-amber',
            'completed'  => 'h-badge-green',
            'failed'     => 'h-badge-red',
            default      => 'h-badge-gray',
        };
    }

    /**
     * Whether the diagnostic has audio attached.
     */
    public function hasAudio(): bool
    {
        return ! empty($this->audio_path);
    }

    /**
     * Whether the diagnostic has vibration data attached.
     */
    public function hasVibration(): bool
    {
        return ! empty($this->vibration_path);
    }

    /**
     * Whether both data sources are present, ready to submit.
     */
    public function isReadyToSubmit(): bool
    {
        return $this->hasAudio() && $this->hasVibration() && $this->status === 'draft';
    }

    /**
     * ML confidence formatted as a percentage.
     */
    public function getConfidencePercentAttribute(): ?string
    {
        return $this->ml_confidence !== null
            ? round($this->ml_confidence * 100, 1) . '%'
            : null;
    }

    /**
     * Canonical engine condition mapping aligned with UMak CET Capstone Scope.
     */
    public const CONDITIONS = [
        'normal' => [
            'key'       => 'normal',
            'label'     => 'Normal Engine Operation',
            'sub_label' => 'Baseline Idling & Revving',
            'badge'     => 'h-badge-green',
            'color'     => '#22c55e',
            'bg'        => 'var(--h-green-bg)',
            'border'    => 'rgba(34,197,94,0.25)',
            'status'    => 'OPTIMAL HEALTH · SAFE TO DRIVE',
        ],
        'misfire' => [
            'key'       => 'misfire',
            'label'     => 'Engine Misfire',
            'sub_label' => 'Combustion Dropout Anomaly',
            'badge'     => 'h-badge-amber',
            'color'     => '#f59e0b',
            'bg'        => 'var(--h-amber-bg)',
            'border'    => 'rgba(245,158,11,0.25)',
            'status'    => 'HIGH ATTENTION · DRIVE WITH CAUTION',
        ],
        'loose_component' => [
            'key'       => 'loose_component',
            'label'     => 'Loose or Worn Component Vibration',
            'sub_label' => 'Structural Mount / Accessory Harmonic',
            'badge'     => 'h-badge-amber',
            'color'     => '#f59e0b',
            'bg'        => 'var(--h-amber-bg)',
            'border'    => 'rgba(245,158,11,0.25)',
            'status'    => 'MODERATE CAUTION · SERVICE RECOMMENDED',
        ],
        'valve_clearance' => [
            'key'       => 'valve_clearance',
            'label'     => 'Improper Valve Clearance',
            'sub_label' => 'Valvetrain Lash / Acoustic Tap Anomaly',
            'badge'     => 'h-badge-red',
            'color'     => '#ef4444',
            'bg'        => 'var(--h-red-bg)',
            'border'    => 'rgba(239,68,68,0.25)',
            'status'    => 'MECHANICAL WEAR · ADJUSTMENT REQUIRED',
        ],
    ];

    /**
     * Normalize condition key to the 4 canonical capstone classes.
     */
    public static function canonicalCondition(?string $raw): string
    {
        $raw = strtolower(trim((string) $raw));
        return match ($raw) {
            'misfiring', 'misfire'                           => 'misfire',
            'idling_rough', 'rough_idle', 'loose_component' => 'loose_component',
            'knocking', 'knock', 'valve_clearance'          => 'valve_clearance',
            'overheating'                                    => 'loose_component',
            default                                          => 'normal',
        };
    }

    /**
     * Formal Academic Condition Label.
     */
    public function getConditionLabelAttribute(): string
    {
        $key = self::canonicalCondition($this->ml_condition);
        return self::CONDITIONS[$key]['label'] ?? 'Normal Engine Operation';
    }

    /**
     * Sub-label describing the technical condition state.
     */
    public function getConditionSubLabelAttribute(): string
    {
        $key = self::canonicalCondition($this->ml_condition);
        return self::CONDITIONS[$key]['sub_label'] ?? 'Baseline Idling & Revving';
    }

    /**
     * Condition badge CSS class.
     */
    public function getConditionBadgeAttribute(): string
    {
        $key = self::canonicalCondition($this->ml_condition);
        return self::CONDITIONS[$key]['badge'] ?? 'h-badge-green';
    }

    /**
     * Full condition meta array.
     */
    public function getConditionMetaAttribute(): array
    {
        $key = self::canonicalCondition($this->ml_condition);
        return self::CONDITIONS[$key] ?? self::CONDITIONS['normal'];
    }
}
