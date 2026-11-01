<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Record a standardized ISO 27001 audit trail event.
     */
    public static function log(string $event, array $details = [], ?int $userId = null, string $status = 'success'): AuditLog
    {
        $userId = $userId ?? Auth::id();
        $ip = Request::ip();
        $userAgent = Request::header('User-Agent');

        return AuditLog::create([
            'user_id'    => $userId,
            'event'      => $event,
            'status'     => $status,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'details'    => $details,
            'created_at' => now(),
        ]);
    }
}
