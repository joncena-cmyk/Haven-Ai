<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        ];
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
