<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MemoryCreated;
use App\Events\QuestionAnswered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Memories\StoreMemoryRequest;
use App\Http\Resources\MemoryResource;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MemoryController extends Controller
{
    public function store(StoreMemoryRequest $request): JsonResponse
    {
        $user = $request->user();
        $couple = $user->activeCouple;

        $memory = DB::transaction(function () use ($request, $user, $couple) {
            $question = Question::where('ulid', $request->string('question_ulid'))->firstOrFail();

            $answerB = $request->string('answer_b')->toString();

            // game_session_id stays null here; the session requirement lands in
            // step 9 (prompt #5).
            $memory = $couple->memories()->create([
                'user_id' => $user->id,
                'question_id' => $question->id,
                'game_session_id' => null,
                'origin' => 'session',
                'answer_a' => $request->string('answer_a')->toString(),
                'answer_b' => $answerB !== '' ? $answerB : null,
                'player_a_name' => $user->nickname,
                'player_b_name' => $couple->partner_name_local,
                'answered_at' => $request->date('answered_at'),
            ]);

            $memory->setRelation('question', $question);

            return $memory;
        });

        QuestionAnswered::dispatch($memory);
        MemoryCreated::dispatch($memory);

        return response()->json([
            'memory' => new MemoryResource($memory),
        ], Response::HTTP_CREATED);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 50));

        // id is the cursor tiebreaker so memories sharing an answered_at don't
        // get skipped across pages.
        $paginator = $request->user()->activeCouple->memories()
            ->with('question.category')
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);

        return response()->json([
            'data' => MemoryResource::collection($paginator->items()),
            'meta' => [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }
}
