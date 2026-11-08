<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAuditController extends Controller
{
    /**
     * Display the ISO/IEC 27001 Admin Security & Audit Console.
     * (Access is enforced by the `admin` route middleware.)
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $event = trim((string) $request->query('event', ''));

        $query = AuditLog::with(['user', 'admin'])->latest('created_at')->latest('id');

        if ($status !== '' && in_array($status, ['success', 'failure'], true)) {
            $query->where('status', $status);
        }

        if ($event !== '') {
            $query->where('event', $event);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('event', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('admin', function ($aq) use ($search) {
                        $aq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $auditLogs = $query->paginate(25)->withQueryString();
        $eventTypes = AuditLog::select('event')->distinct()->orderBy('event')->pluck('event');
        $admins = Admin::withCount('auditLogs')->orderBy('id')->get();

        $stats = [
            'total_events' => AuditLog::count(),
            'failed_events' => AuditLog::where('status', 'failure')->count(),
            'total_users' => User::count(),
            'admin_users' => $admins->count(),
            'total_vehicles' => Vehicle::count(),
        ];

        return view('admin.audit-logs', [
            'auditLogs' => $auditLogs,
            'eventTypes' => $eventTypes,
            'admins' => $admins,
            'stats' => $stats,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'event' => $event,
            ],
        ]);
    }

    /**
     * Export ISO/IEC 27001 forensic audit logs as a CSV file.
     */
    public function export(Request $request): StreamedResponse
    {
        AuditLogger::log('admin.audit_exported', [
            'exported_by' => Auth::guard('admin')->user()?->email,
        ]);

        $filename = 'haven_iso27001_audit_logs_'.now()->format('Ymd_His').'.csv';
        $logs = AuditLog::with(['user', 'admin'])->latest('created_at')->latest('id')->limit(2000)->get();

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Timestamp (ISO 8601)', 'Actor Type', 'Actor ID', 'Actor Email', 'Event', 'Status', 'IP Address', 'User Agent', 'Details JSON']);

            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->id,
                    optional($log->created_at)->toIso8601String(),
                    $log->actor_type,
                    $log->admin_id ?? $log->user_id,
                    $log->actor_email ?? 'guest/system',
                    $log->event,
                    $log->status,
                    $log->ip_address,
                    $log->user_agent,
                    json_encode($log->details ?? []),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
