<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningProgress extends Model
{
    use HasFactory;

    protected $table = 'learning_progress';

    protected $fillable = [
        'user_id',
        'completed_modules',
        'quiz_best_score',
        'quiz_total',
        'quiz_attempts',
        'last_quiz_at',
    ];

    protected $casts = [
        'completed_modules' => 'array',
        'quiz_best_score'   => 'integer',
        'quiz_total'        => 'integer',
        'quiz_attempts'     => 'integer',
        'last_quiz_at'      => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

