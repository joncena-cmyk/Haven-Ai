<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Diagnostic;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\MlBackendService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(MlBackendService $mlService): View
    {
        $since = now()->subDay();

        $stats = [
            'users'             => User::count(),
            'admins'            => Admin::count(),
            'suspended'         => User::whereNotNull('suspended_at')->count(),
            'vehicles'          => Vehicle::count(),
            'diagnostics'       => Diagnostic::count(),
            'diagnostics_7d'    => Diagnostic::where('created_at', '>=', now()->subDays(7))->count(),
            'failed_logins_24h' => AuditLog::whereIn('event', ['auth.login_failed', 'admin.login_failed'])->where('created_at', '>=', $since)->count(),
            'events_24h'        => AuditLog::where('created_at', '>=', $since)->count(),
            'pending_jobs'      => DB::table('jobs')->count(),
            'failed_jobs'       => DB::table('failed_jobs')->count(),
        ];

        return view('admin.dashboard', [
            'stats'        => $stats,
            'mlInfo'       => $mlService->getHealthAndInfo(),
            'recentEvents' => AuditLog::with(['user', 'admin'])->latest('created_at')->latest('id')->limit(8)->get(),
            'newUsers'     => User::latest('id')->limit(5)->get(),
            'alerts'       => AuditLog::with(['user', 'admin'])->where('status', 'failure')
                                ->where('created_at', '>=', $since)->latest('id')->limit(5)->get(),
        ]);
    }
}
