<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'admin_id',
        'event',
        'status',
        'ip_address',
        'user_agent',
        'details',
        'created_at',
    ];

    protected $casts = [
        'details'    => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** Human-readable actor name (admin, driver, or guest/system). */
    public function getActorNameAttribute(): ?string
    {
        if ($this->admin) {
            return $this->admin->name;
        }

        return $this->user?->name;
    }

    /** Actor email, or null when unauthenticated/system. */
    public function getActorEmailAttribute(): ?string
    {
        return $this->admin?->email ?? $this->user?->email;
    }

    /** "admin" | "user" | "guest" */
    public function getActorTypeAttribute(): string
    {
        if ($this->admin_id) {
            return 'admin';
        }

        return $this->user_id ? 'user' : 'guest';
    }
}
