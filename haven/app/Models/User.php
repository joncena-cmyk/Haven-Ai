<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            foreach ($user->vehicles as $vehicle) {
                if (! empty($vehicle->image_path)) {
                    Storage::disk('public')->delete($vehicle->image_path);
                }
            }
            foreach ($user->diagnostics as $diagnostic) {
                $diagnostic->deleteFiles();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'two_factor_enabled',
        'two_factor_channel',
        'two_factor_code',
        'two_factor_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'suspended_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_expires_at' => 'datetime',
        ];
    }

    /**
     * Generate a new 6-digit two-factor OTP code, store its hash, and set 10-minute expiration.
     */
    public function generateTwoFactorCode(): string
    {
        $code = (string) random_int(100000, 999999);

        $this->forceFill([
            'two_factor_code' => Hash::make($code),
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        return $code;
    }

    /**
     * Clear active two-factor code and expiration.
     */
    public function resetTwoFactorCode(): void
    {
        $this->forceFill([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ])->save();
    }

    /**
     * Check if the provided code matches and has not expired.
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        if (empty($this->two_factor_code) || empty($this->two_factor_expires_at)) {
            return false;
        }

        if (now()->isAfter($this->two_factor_expires_at)) {
            return false;
        }

        return Hash::check($code, $this->two_factor_code);
    }

    /**
     * Determine whether the account is currently suspended.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Get all vehicles belonging to this user.
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Get all diagnostics belonging to this user.
     */
    public function diagnostics(): HasMany
    {
        return $this->hasMany(Diagnostic::class);
    }

    /**
     * Get all ISO security audit logs for this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Get learning hub progress & quiz records for this user.
     */
    public function learningProgress(): HasOne
    {
        return $this->hasOne(LearningProgress::class);
    }

    /**
     * Get aggregated learning hub metrics.
     */
    public function getLearningStats(): array
    {
        $progress = $this->learningProgress;
        $completed = is_array($progress?->completed_modules) ? $progress->completed_modules : [];
        $completedCount = count($completed);
        $totalModules = 12;
        $quizBestScore = (int) ($progress?->quiz_best_score ?? 0);
        $quizTotal = (int) ($progress?->quiz_total ?? 10);
        $quizAttempts = (int) ($progress?->quiz_attempts ?? 0);
        $quizPercentage = $quizTotal > 0 ? (int) round(($quizBestScore / $quizTotal) * 100) : 0;

        return [
            'completed_slugs' => $completed,
            'completed_modules' => $completed,
            'completed_count' => $completedCount,
            'total_modules' => $totalModules,
            'quiz_best_score' => $quizBestScore,
            'quiz_total' => $quizTotal,
            'quiz_attempts' => $quizAttempts,
            'quiz_percentage' => $quizPercentage,
            'last_quiz_at' => $progress?->last_quiz_at,
        ];
    }
}
