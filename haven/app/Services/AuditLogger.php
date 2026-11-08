<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Record a standardized ISO 27001 audit trail event.
     *
     * Actor resolution: inside /admin/* the acting identity is the administrator;
     * everywhere else it is the signed-in driver. Explicit IDs always win.
     */
    public static function log(string $event, array $details = [], ?int $userId = null, string $status = 'success', ?int $adminId = null): AuditLog
    {
        if ($userId === null && $adminId === null) {
            if (Request::is('admin') || Request::is('admin/*')) {
                $adminId = Auth::guard('admin')->id();
            } else {
                $userId = Auth::guard('web')->id();
            }
        }

        return AuditLog::create([
            'user_id' => $userId,
            'admin_id' => $adminId,
            'event' => $event,
            'status' => $status,
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}
