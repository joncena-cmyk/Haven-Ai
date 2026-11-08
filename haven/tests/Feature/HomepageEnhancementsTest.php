<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_baseline_demo_and_workflow_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Baseline Demo');
        $response->assertSee('Sample Profile · 4-Cyl ICE Idle');
        $response->assertSee('88.5 °C');
        $response->assertSee('780');
        $response->assertSee('How HAVEN AI Works');
        $response->assertSee('Capture &amp; Connect', false);
        $response->assertSee('Dual-Tier ML Extraction');
        $response->assertSee('Verdict &amp; Action Plan', false);
        $response->assertSee('Ready to Decode Your Powertrain?');
    }

    public function test_homepage_shows_live_telemetry_status_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('Live Powertrain Telemetry');
        $response->assertSee('Hardware DAQ Link Active');
    }

    public function test_login_page_renders_back_home_link_and_legal_links(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Back to homepage');
        $response->assertSee('Terms &amp; Conditions', false);
        $response->assertSee('Privacy Policy');
        $response->assertSee('Caps Lock is ON');
    }

    public function test_guests_can_browse_learning_hub_without_authentication(): void
    {
        $this->get('/learning')
            ->assertOk()
            ->assertSee('Automotive Diagnostic &amp; Telemetry Topics', false)
            ->assertSee('Sign In');

        $this->get('/learning/dtc')
            ->assertOk()
            ->assertSee('OBD-II Diagnostic Trouble Codes (DTC)');

        $this->get('/learning/quiz')
            ->assertOk()
            ->assertSee('Diagnostic Signal &amp; Telemetry Mastery Quiz', false);

        $this->get('/learning/topic/mechanical-faults')
            ->assertOk()
            ->assertSee('Mechanical Faults');

        $this->get('/learning/valve-clearance')
            ->assertOk()
            ->assertSee('Improper Valve Clearance');
    }
}
