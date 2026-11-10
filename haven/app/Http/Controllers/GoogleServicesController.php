<?php

namespace App\Http\Controllers;

use App\Services\GoogleMapsService;
use App\Services\GoogleSpeechService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authenticated proxy endpoints for Google Maps (Places) and Cloud Speech (STT/TTS).
 */
class GoogleServicesController extends Controller
{
    public function status(GoogleMapsService $maps, GoogleSpeechService $speech): JsonResponse
    {
        return response()->json([
            'maps' => $maps->isConfigured(),
            'speech' => $speech->isConfigured(),
        ]);
    }

    public function nearbyShops(Request $request, GoogleMapsService $maps): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:15'],
        ]);

        $result = $maps->nearbyRepairShops((float) $data['lat'], (float) $data['lng'], (int) ($data['limit'] ?? 8));

        return response()->json($result, $result['ok'] ? 200 : ($result['error'] === 'not_configured' ? 503 : 502));
    }

    public function transcribe(Request $request, GoogleSpeechService $speech): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:8192'], // 8 MB; sync STT is limited to ~1 min anyway
        ]);

        $file = $request->file('audio');
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());
        $result = $speech->transcribe((string) file_get_contents($file->getRealPath()), $mime);

        return response()->json($result, $result['ok'] ? 200 : ($result['error'] === 'not_configured' ? 503 : 502));
    }

    public function speak(Request $request, GoogleSpeechService $speech): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:3000'],
        ]);

        $result = $speech->synthesize($data['text']);

        return response()->json($result, $result['ok'] ? 200 : ($result['error'] === 'not_configured' ? 503 : 502));
    }
}

