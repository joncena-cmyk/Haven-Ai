<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleDeviceKeySeeder extends Seeder
{
    public function run(): void
    {
        foreach (Vehicle::whereNull('device_key')->get() as $vehicle) {
            $vehicle->device_key = 'hvn_dev_'.bin2hex(random_bytes(16));
            $vehicle->save();
        }
    }
}
