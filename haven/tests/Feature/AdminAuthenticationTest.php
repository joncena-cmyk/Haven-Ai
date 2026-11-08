<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_page_renders(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Admin Console')
            ->assertDontSee('Create one');
    }

    public function test_admin_can_sign_in_on_dedicated_guard_and_login_is_audited(): void
    {
        $admin = Admin::factory()->create(['email' => 'root@haven.test']);

        $this->post(route('admin.login.store'), ['email' => 'root@haven.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('web');
        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['admin_id' => $admin->id, 'event' => 'admin.login', 'status' => 'success']);
    }

    public function test_driver_credentials_do_not_work_on_admin_login(): void
    {
        User::factory()->create(['email' => 'driver@haven.test']);

        $this->post(route('admin.login.store'), ['email' => 'driver@haven.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('admin');
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.login_failed', 'status' => 'failure']);
    }

    public function test_admin_credentials_do_not_work_on_driver_login(): void
    {
        Admin::factory()->create(['email' => 'root@haven.test']);

        $this->post('/login', ['email' => 'root@haven.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_admin_session_cannot_reach_driver_pages(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')->get(route('vehicles.index'))->assertRedirect(route('login'));
        $this->actingAs($admin, 'admin')->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_login_is_throttled_after_repeated_failures(): void
    {
        Admin::factory()->create(['email' => 'root@haven.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login.store'), ['email' => 'root@haven.test', 'password' => 'nope']);
        }

        $this->post(route('admin.login.store'), ['email' => 'root@haven.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('admin');
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.login_throttled']);
    }

    public function test_admin_can_sign_out_and_is_audited(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
        $this->assertDatabaseHas('audit_logs', ['admin_id' => $admin->id, 'event' => 'admin.logout']);
    }

    public function test_driver_registration_never_creates_an_administrator(): void
    {
        $this->post('/register', [
            'name' => 'First Driver',
            'email' => 'first@haven.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'first@haven.test']);
        $this->assertSame(0, Admin::count());
    }

    public function test_artisan_command_creates_a_dedicated_admin(): void
    {
        $this->artisan('admin:create', ['email' => 'owner@haven.test', '--name' => 'Owner', '--password' => 'StrongPass123'])
            ->assertSuccessful();

        $this->assertDatabaseHas('admins', ['email' => 'owner@haven.test', 'name' => 'Owner']);
        $this->assertDatabaseMissing('users', ['email' => 'owner@haven.test']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'admin.account_created_cli']);
    }

    public function test_artisan_command_rejects_weak_password(): void
    {
        $this->artisan('admin:create', ['email' => 'owner@haven.test', '--password' => 'weak'])
            ->assertFailed();

        $this->assertSame(0, Admin::count());
    }
}
