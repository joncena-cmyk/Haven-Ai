<?php

namespace App\Http\Controllers;

use App\Services\GlobalAiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiAssistantController extends Controller
{
    public function __construct(protected GlobalAiAssistantService $assistantService) {}

    /**
     * Handle website-wide AI Orb questions.
     */
    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string|max:600',
        ]);

        $question = trim($request->input('question'));
        $answer = $this->assistantService->answer($question, Auth::id());

        return response()->json([
            'success' => true,
            'question' => $question,
            'answer' => $answer,
            'user' => Auth::user()?->name ?? 'Guest',
        ]);
    }
}
