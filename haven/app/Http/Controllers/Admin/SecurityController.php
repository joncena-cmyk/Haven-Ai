<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SecurityController extends Controller
{
    /**
     * Display Admin Security Roster & Password Management.
     */
    public function index(): View
    {
        $admins = Admin::orderBy('id', 'asc')->get();
        $recentAdminAudits = AuditLog::with('admin')
            ->where('event', 'like', 'admin.%')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('admin.security.index', [
            'admins' => $admins,
            'recentAdminAudits' => $recentAdminAudits,
            'currentAdmin' => auth('admin')->user(),
        ]);
    }

    /**
     * Rotate current administrator password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $admin = auth('admin')->user();
        $admin->update([
            'password' => Hash::make($validated['password']),
        ]);

        AuditLogger::log('admin.password_changed', [
            'admin_id' => $admin->id,
            'email' => $admin->email,
        ], null, 'success', $admin->id);

        return back()->with('status', 'Your administrator password has been updated securely.');
    }

    /**
     * Provision a new administrator account (Multi-Admin Management).
     */
    public function storeAdmin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $newAdmin = Admin::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        AuditLogger::log('admin.account_created', [
            'new_admin_id' => $newAdmin->id,
            'email' => $newAdmin->email,
            'created_by' => auth('admin')->user()?->email,
        ], null, 'success', auth('admin')->id());

        return back()->with('status', "New administrator account [{$newAdmin->email}] successfully provisioned.");
    }
}
