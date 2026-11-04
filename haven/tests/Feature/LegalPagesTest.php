<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_terms_and_conditions_page_renders_successfully(): void
    {
        $response = $this->get('/terms');

        $response->assertOk();
        $response->assertSee('Terms and Conditions of Use');
        $response->assertSee('Critical Automotive Safety Disclaimer');
        $response->assertSee('Not a Replacement for a Certified Mechanic');
        $response->assertSee('ISO/SAE 21434');
        $response->assertSee('Limitation of Liability');
    }

    public function test_privacy_policy_page_renders_successfully(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertSee('Privacy & Data Protection Policy', false);
        $response->assertSee('Zero-PII Transmission');
        $response->assertSee('Private Storage Disks');
        $response->assertSee('ISO/IEC 27001');
    }
}
