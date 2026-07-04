<?php

namespace App\Modules\Game\Http\Controllers;

use App\Modules\Catalog\Data\QuestionData;
use App\Modules\Game\Http\Resources\SessionResource;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Queries\GetNextQuestionInSessionQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class QuestionController
{
    public function next(Request $request): JsonResponse
    {
        $couple = Couple::findOrFail($request->user()->active_couple_id);
        $session = $couple->gameSessions()->active()->first();

        if ($session === null) {
            return response()->json([
                'message' => 'Najpierw rozpocznij sesję.',
                'errors' => ['session' => ['Brak aktywnej sesji.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $state = $session->state;
        /** @var list<int> $remainingIds */
        $remainingIds = is_array($state['remaining_ids'] ?? null) ? $state['remaining_ids'] : [];
        $currentIndex = isset($state['current_index']) ? (int) $state['current_index'] : 0;

        // Pure read: /next never advances current_index — only save or skip does.
        if ($currentIndex >= count($remainingIds)) {
            return response()->json([
                'question' => null,
                'session_complete' => true,
                'session' => new SessionResource($session),
            ]);
        }

        $question = GetNextQuestionInSessionQuery::run($session);

        if ($question === null) {
            // The current id no longer resolves — should never happen (questions
            // are not deleted). Surface it instead of guessing.
            Log::error('Question from session pool not found', [
                'session_id' => $session->id,
                'question_id' => $remainingIds[$currentIndex],
            ]);

            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Pytanie z puli nie istnieje, zgłoś bug.');
        }

        return response()->json([
            'question' => $this->questionPayload($question),
            'session' => new SessionResource($session),
        ]);
    }

    /**
     * Reproduce the Catalog QuestionResource shape from QuestionData (byte-1:1
     * with P3) — category trimmed to slug + name.
     *
     * @return array<string, mixed>
     */
    private function questionPayload(QuestionData $question): array
    {
        return [
            'ulid' => $question->ulid,
            'body' => $question->body,
            'type' => $question->type,
            'category' => $question->category ? [
                'slug' => $question->category->slug,
                'name' => $question->category->name,
            ] : null,
            'tags' => $question->tags,
        ];
    }
}
