<?php

namespace Tests\Feature;

use App\Models\Diagnostic;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageCleanupOnDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_deletion_purges_photo_and_all_diagnostic_audio_and_vibration_files(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create();

        // 1. Create vehicle with photo
        $photo = UploadedFile::fake()->create('sedan.jpg', 50, 'image/jpeg');
        $photoPath = $photo->store('vehicles', 'public');

        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2023,
            'image_path' => $photoPath,
        ]);

        Storage::disk('public')->assertExists($photoPath);

        // 2. Create diagnostics with audio & vibration files
        $audioPath = 'diagnostics/'.$vehicle->id.'/audio/sample.webm';
        $vibPath = 'diagnostics/'.$vehicle->id.'/vibration/sample.csv';
        Storage::disk('local')->put($audioPath, 'fake-audio-bytes');
        Storage::disk('local')->put($vibPath, "timestamp,x,y,z\n0,0.1,0.2,1.0\n");

        $diagnostic = Diagnostic::create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'audio_path' => $audioPath,
            'vibration_path' => $vibPath,
            'status' => 'completed',
        ]);

        Storage::disk('local')->assertExists($audioPath);
        Storage::disk('local')->assertExists($vibPath);

        // 3. Delete vehicle via controller
        $response = $this->actingAs($user)->delete(route('vehicles.destroy', $vehicle));
        $response->assertRedirect(route('vehicles.index'));

        // 4. Assert vehicle and diagnostic records are gone
        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
        $this->assertDatabaseMissing('diagnostics', ['id' => $diagnostic->id]);

        // 5. Assert physical files are purged from disk
        Storage::disk('public')->assertMissing($photoPath);
        Storage::disk('local')->assertMissing($audioPath);
        Storage::disk('local')->assertMissing($vibPath);
    }

    public function test_user_deletion_purges_vehicles_photos_and_all_diagnostics_files(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create([
            'password' => bcrypt('StrongPass123!'),
        ]);

        $photoPath = UploadedFile::fake()->create('car.jpg', 50, 'image/jpeg')->store('vehicles', 'public');
        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'make' => 'Toyota',
            'model' => 'Vios',
            'year' => 2022,
            'image_path' => $photoPath,
        ]);

        $audioPath = 'diagnostics/'.$vehicle->id.'/audio/engine.webm';
        $vibPath = 'diagnostics/'.$vehicle->id.'/vibration/vib.csv';
        Storage::disk('local')->put($audioPath, 'fake-audio-bytes');
        Storage::disk('local')->put($vibPath, "timestamp,x,y,z\n0,0,0,1\n");

        Diagnostic::create([
            'user_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'audio_path' => $audioPath,
            'vibration_path' => $vibPath,
            'status' => 'completed',
        ]);

        // Delete user account
        $response = $this->actingAs($user)->delete(route('profile.destroy'), [
            'password' => 'StrongPass123!',
        ]);
        $response->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);

        Storage::disk('public')->assertMissing($photoPath);
        Storage::disk('local')->assertMissing($audioPath);
        Storage::disk('local')->assertMissing($vibPath);
    }
}
