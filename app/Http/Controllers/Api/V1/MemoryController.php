<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MemoryCreated;
use App\Events\QuestionAnswered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Memories\StoreMemoryRequest;
use App\Http\Resources\MemoryResource;
use App\Http\Resources\SessionResource;
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

        if ($currentIndex >= count($remainingIds)) {
            return response()->json([
                'message' => 'Sesja wyczerpana, zakończ ją zanim zapiszesz wspomnienie.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $expectedQuestionId = $remainingIds[$currentIndex];
        $question = Question::where('ulid', $request->string('question_ulid'))->firstOrFail();

        // Race guard: the client may have submitted a stale card after a refresh.
        if ($question->id !== $expectedQuestionId) {
            return response()->json([
                'message' => 'Pytanie nie pasuje do aktualnej karty sesji. Pobierz ponownie /questions/next.',
            ], Response::HTTP_CONFLICT);
        }

        $memory = DB::transaction(function () use ($request, $user, $couple, $session, $question, $state, $currentIndex) {
            $answerB = $request->string('answer_b')->toString();

            $memory = $couple->memories()->create([
                'user_id' => $user->id,
                'question_id' => $question->id,
                'game_session_id' => $session->id,
                'origin' => 'session',
                'answer_a' => $request->string('answer_a')->toString(),
                'answer_b' => $answerB !== '' ? $answerB : null,
                'player_a_name' => $user->nickname,
                'player_b_name' => $couple->partner_name_local,
                'answered_at' => $request->date('answered_at'),
            ]);

            $memory->setRelation('question', $question);

            // Mark the question seen forever (idempotent on the composite PK) and
            // advance the session: re-assign the whole state array so Eloquent
            // tracks the change.
            $couple->seenQuestions()->syncWithoutDetaching([
                $question->id => ['seen_at' => now()],
            ]);

            $state['current_index'] = $currentIndex + 1;
            $session->state = $state;
            $session->cards_drawn_count++;
            $session->cards_saved_count++;
            $session->save();

            return $memory;
        });

        QuestionAnswered::dispatch($memory);
        MemoryCreated::dispatch($memory);

        return response()->json([
            'memory' => new MemoryResource($memory),
            'session' => new SessionResource($session),
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
