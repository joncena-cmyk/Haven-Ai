<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleShowroomAndConfiguratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_showroom_fleet(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'make' => 'Audi',
            'model' => 'A7 Sportback',
            'year' => 2025,
            'body_type' => 'coupe',
            'color' => 'Solar Yellow',
            'engine_type' => '3.0L TFSI V6',
        ]);

        $response = $this->actingAs($user)->get(route('vehicles.index'));

        $response->assertOk()
            ->assertSee('My Vehicles')
            ->assertSee('2025 Audi A7 Sportback')
            ->assertSee('3.0L TFSI V6')
            ->assertSee('Register Vehicle'); // ghost slot
    }

    public function test_user_can_create_vehicle_with_body_type_and_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $photo = UploadedFile::fake()->create('my_audi.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user)->post(route('vehicles.store'), [
            'make' => 'Audi',
            'model' => 'A7 Sportback',
            'year' => 2025,
            'body_type' => 'coupe',
            'color' => 'Cobalt Blue',
            'plate_number' => 'XYZ 9999',
            'transmission' => 'Automatic',
            'mileage_km' => 12500,
            'photo' => $photo,
        ]);

        $response->assertRedirect(route('vehicles.index'));

        $vehicle = Vehicle::where('user_id', $user->id)->first();
        $this->assertNotNull($vehicle);
        $this->assertSame('coupe', $vehicle->body_type);
        $this->assertNotNull($vehicle->image_path);
        Storage::disk('public')->assertExists($vehicle->image_path);
    }

    public function test_user_can_update_vehicle_and_remove_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('initial.jpg', 100, 'image/jpeg')->store('vehicles', 'public');

        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2022,
            'body_type' => 'sedan',
            'image_path' => $file,
        ]);

        Storage::disk('public')->assertExists($file);

        $response = $this->actingAs($user)->patch(route('vehicles.update', $vehicle), [
            'make' => 'Toyota',
            'model' => 'Corolla Altis',
            'year' => 2023,
            'body_type' => 'sedan',
            'remove_photo' => 1,
        ]);

        $response->assertRedirect(route('vehicles.show', $vehicle));

        $vehicle->refresh();
        $this->assertSame('Corolla Altis', $vehicle->model);
        $this->assertNull($vehicle->image_path);
        Storage::disk('public')->assertMissing($file);
    }

    public function test_deleting_vehicle_cleans_up_photo_from_disk(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('car.jpg', 100, 'image/jpeg')->store('vehicles', 'public');

        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'make' => 'Nissan',
            'model' => 'GT-R',
            'year' => 2021,
            'image_path' => $file,
        ]);

        Storage::disk('public')->assertExists($file);

        $this->actingAs($user)->delete(route('vehicles.destroy', $vehicle))
            ->assertRedirect(route('vehicles.index'));

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
        Storage::disk('public')->assertMissing($file);
    }
}
