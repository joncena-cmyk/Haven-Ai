<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_admin_login_and_driver_is_turned_away_and_audited(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        $user = User::factory()->create();
        foreach (['admin.dashboard', 'admin.users.index'] as $name) {
            $this->actingAs($user)->get(route($name))->assertRedirect(route('admin.login'));
        }

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event'   => 'admin.access_denied',
            'status'  => 'failure',
        ]);
    }

    public function test_admin_can_view_dashboard_users_and_user_detail(): void
    {
        $admin  = Admin::factory()->create();
        $target = User::factory()->create(['name' => 'Target Person']);

        $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()->assertSee('System Overview');
        $this->actingAs($admin, 'admin')->get(route('admin.users.index', ['q' => 'Target']))->assertOk()->assertSee($target->email);
        $this->actingAs($admin, 'admin')->get(route('admin.users.show', $target))->assertOk()->assertSee('Target Person');
    }

    public function test_admin_pages_do_not_expose_driver_navigation(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('New Session')
            ->assertDontSee(route('vehicles.index'), false)
            ->assertSee('ADMINISTRATOR');
    }

    public function test_admin_can_suspend_and_reinstate_user_with_audit_trail(): void
    {
        $admin  = Admin::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($admin, 'admin')->post(route('admin.users.suspend', $target))->assertRedirect();
        $this->assertTrue($target->fresh()->isSuspended());
        $this->assertDatabaseHas('audit_logs', ['admin_id' => $admin->id, 'event' => 'admin.user_suspended']);

        $this->actingAs($admin, 'admin')->post(route('admin.users.unsuspend', $target))->assertRedirect();
        $this->assertFalse($target->fresh()->isSuspended());
        $this->assertDatabaseHas('audit_logs', ['admin_id' => $admin->id, 'event' => 'admin.user_unsuspended']);
    }

    public function test_suspended_user_cannot_log_in_and_existing_session_is_ended(): void
    {
        $user = User::factory()->create([
            'email'        => 'blocked@haven.test',
            'suspended_at' => now(),
        ]);

        $this->post('/login', ['email' => 'blocked@haven.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login_blocked_suspended']);
    }

    public function test_admin_can_view_ml_management_dashboard_with_model_meta(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.ml.index'))
            ->assertOk()
            ->assertSee('Machine Learning &amp; Signal Processing Engine', false)
            ->assertSee('Global Random Forest Feature Weights')
            ->assertSee('Confusion Matrix')
            ->assertSee('Evaluation Scheme:');
    }
}
