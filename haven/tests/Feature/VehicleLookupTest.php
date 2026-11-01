<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VehicleLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_use_the_vehicle_catalog(): void
    {
        $this->getJson(route('vehicles.catalog.makes'))->assertUnauthorized();
    }

    public function test_authenticated_users_can_list_makes(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'vpic.nhtsa.dot.gov/api/vehicles/GetMakesForVehicleType/car*' => Http::response([
                'Results' => [['MakeName' => 'HONDA'], ['MakeName' => 'TOYOTA']],
            ]),
            'vpic.nhtsa.dot.gov/api/vehicles/GetMakesForVehicleType/truck*' => Http::response([
                'Results' => [['MakeName' => 'FORD']],
            ]),
            'vpic.nhtsa.dot.gov/api/vehicles/GetMakesForVehicleType/mpv*' => Http::response([
                'Results' => [],
            ]),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson(route('vehicles.catalog.makes'));

        $response->assertOk()
            ->assertJsonFragment(['Honda'])
            ->assertJsonFragment(['Toyota'])
            ->assertJsonFragment(['Ford']);
    }

    public function test_authenticated_users_can_list_models_for_a_make_and_year(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'vpic.nhtsa.dot.gov/api/vehicles/GetModelsForMakeYear/*' => Http::response([
                'Results' => [
                    ['Model_Name' => 'Civic'],
                    ['Model_Name' => 'Accord'],
                ],
            ]),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson(route('vehicles.catalog.models', ['make' => 'Honda', 'year' => 2018]));

        $response->assertOk()
            ->assertJsonPath('models.0', 'Accord')
            ->assertJsonPath('models.1', 'Civic');
    }

    public function test_vin_decode_fills_vehicle_fields(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'vpic.nhtsa.dot.gov/api/vehicles/DecodeVinValues/*' => Http::response([
                'Results' => [[
                    'ErrorCode' => '0',
                    'Make' => 'HONDA',
                    'Model' => 'Civic',
                    'ModelYear' => '2003',
                    'DisplacementL' => '1.7',
                    'EngineCylinders' => '4',
                    'EngineModel' => '',
                    'TransmissionStyle' => 'Manual',
                ]],
            ]),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson(route('vehicles.catalog.vin', ['vin' => '1HGEM22913L123456']));

        $response->assertOk()
            ->assertJson([
                'make' => 'Honda',
                'model' => 'Civic',
                'year' => 2003,
                'engine_type' => '1.7L 4-cyl',
                'transmission' => 'Manual',
            ]);
    }

    public function test_invalid_vin_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('vehicles.catalog.vin', ['vin' => 'SHORT']))
            ->assertStatus(422);
    }
}
