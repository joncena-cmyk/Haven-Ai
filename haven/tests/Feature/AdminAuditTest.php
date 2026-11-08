<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_cannot_access_admin_audit_console(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.audit.index'))
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'admin.access_denied',
            'status' => 'failure',
        ]);
    }

    public function test_admin_can_view_and_export_audit_logs(): void
    {
        $admin = Admin::factory()->create(['name' => 'Chief Admin']);
        $targetUser = User::factory()->create();
        AuditLog::create(['user_id' => $targetUser->id, 'event' => 'vehicle.created', 'status' => 'success', 'created_at' => now()]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertSee('Security & Activity Audit Console', false)
            ->assertSee('Administrator Accounts')
            ->assertSee($targetUser->email)
            ->assertDontSee('Make Admin');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.audit.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $admin->id,
            'user_id' => null,
            'event' => 'admin.audit_exported',
            'status' => 'success',
        ]);
    }

    public function test_role_toggle_route_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('admin.users.toggle-admin'));
    }

    public function test_failed_login_and_profile_update_are_recorded_in_audit_trail(): void
    {
        $user = User::factory()->create([
            'email' => 'driver@haven.test',
        ]);

        $this->post('/login', [
            'email' => 'driver@haven.test',
            'password' => 'wrong-password',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'auth.login_failed',
            'status' => 'failure',
        ]);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Updated Driver',
            'email' => 'driver@haven.test',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'profile.updated',
            'status' => 'success',
        ]);
    }
}
