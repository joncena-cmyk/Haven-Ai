<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class VehicleController extends Controller
{
    /**
     * Display a list of the user's vehicles.
     */
    public function index()
    {
        $vehicles = Auth::user()->vehicles()->latest()->get();

        return view('vehicles.index', compact('vehicles'));
    }

    /**
     * Show the form for adding a new vehicle.
     */
    public function create()
    {
        return view('vehicles.create');
    }

    /**
     * Store a newly created vehicle in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nickname'     => 'nullable|string|max:60',
            'make'         => 'required|string|max:60',
            'model'        => 'required|string|max:60',
            'year'         => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'plate_number' => 'nullable|string|max:20',
            'color'        => 'nullable|string|max:40',
            'engine_type'  => 'nullable|string|max:80',
            'transmission' => 'nullable|in:Manual,Automatic,CVT,Other',
            'mileage_km'   => 'nullable|integer|min:0',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $validated['user_id'] = Auth::id();

        $vehicle = Vehicle::create($validated);

        \App\Services\AuditLogger::log('vehicle.created', [
            'vehicle_id' => $vehicle->id,
            'make'       => $vehicle->make,
            'model'      => $vehicle->model,
            'year'       => $vehicle->year,
            'device_key' => $vehicle->device_key,
        ]);

        $successMsg = $vehicle->isScopeCompliant()
            ? 'Vehicle registered successfully!'
            : "Vehicle registered. Advisory: {$vehicle->scope_meta['notice']}";

        return redirect()->route('vehicles.index')
            ->with('success', $successMsg);
    }

    /**
     * Display a specific vehicle's details.
     */
    public function show(Vehicle $vehicle)
    {
        $this->authorizeOwner($vehicle);

        return view('vehicles.show', compact('vehicle'));
    }

    /**
     * Show the form for editing a vehicle.
     */
    public function edit(Vehicle $vehicle)
    {
        $this->authorizeOwner($vehicle);

        return view('vehicles.edit', compact('vehicle'));
    }

    /**
     * Update the specified vehicle in storage.
     */
    public function update(Request $request, Vehicle $vehicle)
    {
        $this->authorizeOwner($vehicle);

        $validated = $request->validate([
            'nickname'     => 'nullable|string|max:60',
            'make'         => 'required|string|max:60',
            'model'        => 'required|string|max:60',
            'year'         => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'plate_number' => 'nullable|string|max:20',
            'color'        => 'nullable|string|max:40',
            'engine_type'  => 'nullable|string|max:80',
            'transmission' => 'nullable|in:Manual,Automatic,CVT,Other',
            'mileage_km'   => 'nullable|integer|min:0',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $vehicle->update($validated);

        \App\Services\AuditLogger::log('vehicle.updated', [
            'vehicle_id' => $vehicle->id,
            'make'       => $vehicle->make,
            'model'      => $vehicle->model,
        ]);

        return redirect()->route('vehicles.show', $vehicle)
            ->with('success', 'Vehicle updated successfully!');
    }

    /**
     * Remove the specified vehicle from storage.
     */
    public function destroy(Vehicle $vehicle)
    {
        $this->authorizeOwner($vehicle);

        \App\Services\AuditLogger::log('vehicle.deleted', [
            'vehicle_id' => $vehicle->id,
            'make'       => $vehicle->make,
            'model'      => $vehicle->model,
        ]);

        $vehicle->delete();

        return redirect()->route('vehicles.index')
            ->with('success', 'Vehicle removed.');
    }

    /**
     * Abort with 403 if the authenticated user doesn't own the vehicle.
     */
    private function authorizeOwner(Vehicle $vehicle): void
    {
        abort_if($vehicle->user_id !== Auth::id(), 403, 'Unauthorized.');
    }

    /**
     * Regenerate the ESP32 device key for a vehicle (invalidates old key).
     * POST /vehicles/{vehicle}/regenerate-key
     */
    public function regenerateKey(Vehicle $vehicle)
    {
        $this->authorizeOwner($vehicle);

        $oldKey = $vehicle->device_key;
        $newKey = 'hvn_dev_' . bin2hex(random_bytes(16));

        $vehicle->update(['device_key' => $newKey]);

        // Clear live telemetry cache — old key is now invalid
        Cache::forget('telemetry:live:latest');

        \App\Services\AuditLogger::log('vehicle.key_regenerated', [
            'vehicle_id' => $vehicle->id,
            'old_key'    => substr($oldKey, 0, 12) . '...',  // partial for audit trail
        ]);

        return redirect()->route('profile.edit')
            ->with('key_regenerated', "New device key generated for {$vehicle->display_name}. Update your ESP32 firmware.")
            ->with('key_vehicle_id', $vehicle->id);
    }

    /**
     * Save sensor alert thresholds for a vehicle.
     * POST /vehicles/{vehicle}/thresholds
     */
    public function saveThresholds(Request $request, Vehicle $vehicle)
    {
        $this->authorizeOwner($vehicle);

        $validated = $request->validate([
            'temp_max_c'      => 'nullable|numeric|min:50|max:150',
            'rpm_max'         => 'nullable|integer|min:500|max:10000',
            'voltage_min_v'   => 'nullable|numeric|min:8|max:16',
            'vibration_max'   => 'nullable|numeric|min:1|max:50',
        ]);

        // Only keep non-null values
        $thresholds = array_filter($validated, fn($v) => $v !== null && $v !== '');

        $vehicle->update(['alert_thresholds' => empty($thresholds) ? null : $thresholds]);

        return redirect()->route('profile.edit')
            ->with('thresholds_saved', "Alert thresholds updated for {$vehicle->display_name}.");
    }

    /**
     * Update 3D Sketchfab Model UID for a vehicle.
     * POST /vehicles/{vehicle}/sketchfab-uid
     */
    public function updateSketchfabUid(Request $request, Vehicle $vehicle)
    {
        $this->authorizeOwner($vehicle);

        $validated = $request->validate([
            'sketchfab_uid' => ['nullable', 'string', 'max:64'],
        ]);

        $vehicle->update(['sketchfab_uid' => $validated['sketchfab_uid'] ? trim($validated['sketchfab_uid']) : null]);

        if ($request->wantsJson()) {
            return response()->json([
                'success'       => true,
                'sketchfab_uid' => $vehicle->sketchfab_uid,
                'model_name'    => $vehicle->sketchfab_model_name,
                'message'       => '3D vehicle model updated successfully.',
            ]);
        }

        return back()->with('status', '3D vehicle model updated.');
    }
}
