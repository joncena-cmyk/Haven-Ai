<?php

namespace App\Http\Controllers;

use App\Services\NhtsaVehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class VehicleLookupController extends Controller
{
    public function makes(NhtsaVehicleService $nhtsa): JsonResponse
    {
        try {
            return response()->json(['makes' => $nhtsa->makes()]);
        } catch (Throwable $e) {
            return response()->json(['makes' => [], 'error' => $e->getMessage()], 502);
        }
    }

    public function models(Request $request, NhtsaVehicleService $nhtsa): JsonResponse
    {
        $validated = $request->validate([
            'make' => ['required', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
        ]);

        try {
            return response()->json([
                'models' => $nhtsa->models(
                    $validated['make'],
                    isset($validated['year']) ? (int) $validated['year'] : null
                ),
            ]);
        } catch (Throwable $e) {
            return response()->json(['models' => [], 'error' => $e->getMessage()], 502);
        }
    }

    public function vin(Request $request, NhtsaVehicleService $nhtsa): JsonResponse
    {
        $validated = $request->validate([
            'vin' => ['required', 'string', 'min:11', 'max:20'],
        ]);

        try {
            return response()->json($nhtsa->decodeVin($validated['vin']));
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}
