<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Game\Actions\SaveLocalGameMemoryAction;
use App\Modules\Game\Actions\SaveMemoryFromAnswerAction;
use App\Modules\Game\Http\Resources\SessionResource;
use App\Modules\Memories\Http\Requests\StoreLocalMemoryRequest;
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

    /**
     * POST /memories/local — an answer written during a local game (P10).
     *
     * Here rather than in Memories for the same reason as the session save: the
     * write spans two modules (the memory, and the card entering Game's played
     * set), and the app layer is where that is composed. The response carries a
     * memory alone — there is no local session on the server to report back.
     *
     * The couple comes from the token, never from the input.
     */
    public function storeLocal(StoreLocalMemoryRequest $request): JsonResponse
    {
        $answerB = $request->string('answer_b')->toString();

        $memory = SaveLocalGameMemoryAction::run(
            $request->user(),
            $request->string('question_ulid')->toString(),
            $request->string('answer_a')->toString(),
            $answerB !== '' ? $answerB : null,
            $request->string('player_b_name')->toString(),
            $request->date('answered_at') ?? now(),
        );

        return response()->json([
            'memory' => new MemoryResource($memory),
        ], Response::HTTP_CREATED);
    }
}
