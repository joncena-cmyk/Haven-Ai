<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $filter = (string) $request->query('filter', '');

        $users = User::withCount(['vehicles', 'diagnostics'])
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($filter === 'suspended', fn ($q) => $q->whereNotNull('suspended_at'))
            ->when($filter === 'active', fn ($q) => $q->whereNull('suspended_at'))
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search', 'filter'));
    }

    public function show(User $user): View
    {
        $user->loadCount(['vehicles', 'diagnostics']);

        return view('admin.users.show', [
            'user' => $user,
            'vehicles' => $user->vehicles()->withCount('diagnostics')->latest('id')->get(),
            'events' => AuditLog::where('user_id', $user->id)->latest('created_at')->latest('id')->limit(20)->get(),
        ]);
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        if ($user->isSuspended()) {
            return back()->with('error', 'This account is already suspended.');
        }

        $user->forceFill(['suspended_at' => now()])->save();

        // Invalidate persistent sessions/tokens immediately.
        $user->forceFill(['remember_token' => null])->save();
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        AuditLogger::log('admin.user_suspended', [
            'target_user_id' => $user->id,
            'target_user_email' => $user->email,
        ]);

        return back()->with('success', "Suspended {$user->name} ({$user->email}).");
    }

    public function unsuspend(Request $request, User $user): RedirectResponse
    {
        if (! $user->isSuspended()) {
            return back()->with('error', 'This account is not suspended.');
        }

        $user->forceFill(['suspended_at' => null])->save();

        AuditLogger::log('admin.user_unsuspended', [
            'target_user_id' => $user->id,
            'target_user_email' => $user->email,
        ]);

        return back()->with('success', "Reinstated {$user->name} ({$user->email}).");
    }
}
