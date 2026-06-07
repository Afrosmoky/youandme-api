<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\SessionResource;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class QuestionController extends Controller
{
    public function next(Request $request): JsonResponse
    {
        $couple = $request->user()->activeCouple;
        $session = $couple->gameSessions()->active()->first();

        if ($session === null) {
            return response()->json([
                'message' => 'Najpierw rozpocznij sesję.',
                'errors' => ['session' => ['Brak aktywnej sesji.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $state = $session->state;
        $currentIndex = (int) ($state['current_index'] ?? 0);
        /** @var list<int> $remainingIds */
        $remainingIds = $state['remaining_ids'] ?? [];

        // Pure read: /next never advances current_index — only save or skip does.
        if ($currentIndex >= count($remainingIds)) {
            return response()->json([
                'question' => null,
                'session_complete' => true,
                'session' => new SessionResource($session),
            ]);
        }

        $questionId = $remainingIds[$currentIndex];
        $question = Question::with('category')->find($questionId);

        if ($question === null) {
            // A question id in the session pool no longer resolves — should never
            // happen (questions are not deleted). Surface it instead of guessing.
            Log::error('Question from session pool not found', [
                'session_id' => $session->id,
                'question_id' => $questionId,
            ]);

            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Pytanie z puli nie istnieje, zgłoś bug.');
        }

        return response()->json([
            'question' => new QuestionResource($question),
            'session' => new SessionResource($session),
        ]);
    }
}
