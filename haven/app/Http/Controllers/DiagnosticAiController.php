<?php

namespace App\Http\Controllers;

use App\Models\Diagnostic;
use App\Services\DiagnosticAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiagnosticAiController extends Controller
{
    public function __construct(protected DiagnosticAiService $aiService)
    {
    }

    /**
     * Answer interactive AI Copilot question about a specific diagnostic session.
     */
    public function ask(Request $request, Diagnostic $diagnostic): JsonResponse
    {
        // Authorize owner
        abort_if($diagnostic->user_id !== Auth::id(), 403, 'Unauthorized access to this diagnostic session.');

        $request->validate([
            'question' => 'required|string|max:500',
        ]);

        $question = trim($request->input('question'));
        $answer = $this->aiService->answerQuestion($diagnostic, $question);

        return response()->json([
            'success' => true,
            'question' => $question,
            'answer' => $answer,
            'condition' => $diagnostic->ml_condition,
        ]);
    }
}
