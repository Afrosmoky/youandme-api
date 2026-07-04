<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Game\Actions\SaveMemoryFromAnswerAction;
use App\Modules\Game\Http\Resources\SessionResource;
use App\Modules\Memories\Http\Requests\StoreMemoryRequest;
use App\Modules\Memories\Http\Resources\MemoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * App composition root for POST /memories — the session-answer flow peer-combines
 * a Memories resource ({memory}) and a Game resource ({session}), so it lives in
 * the app layer (same rule as the couple endpoints, Etap 4). Thin: the guarded
 * save is owned by Game\SaveMemoryFromAnswerAction (guards + state), the write by
 * Memories. GET /memories (a pure list) stays in the Memories module.
 */
final class MemoryController
{
    public function store(StoreMemoryRequest $request): JsonResponse
    {
        $answerB = $request->string('answer_b')->toString();

        [$memory, $session] = SaveMemoryFromAnswerAction::run(
            $request->user(),
            $request->string('question_ulid')->toString(),
            $request->string('answer_a')->toString(),
            $answerB !== '' ? $answerB : null,
            $request->date('answered_at') ?? now(),
        );

        return response()->json([
            'memory' => new MemoryResource($memory),
            'session' => new SessionResource($session),
        ], Response::HTTP_CREATED);
    }
}
