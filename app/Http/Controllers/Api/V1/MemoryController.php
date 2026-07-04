<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Memories\StoreMemoryRequest;
use App\Http\Resources\MemoryResource;
use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Actions\SaveMemoryFromAnswerAction;
use App\Modules\Game\Http\Resources\SessionResource;
use App\Modules\Game\Models\Couple;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * TODO Etap 5 (Memories): this controller (and MemoryResource) still live in the
 * app layer until Memories is extracted. `store` is the session-answer flow —
 * its guards + response stay here (byte-1:1) while the write is delegated to
 * Game\SaveMemoryFromAnswerAction.
 */
class MemoryController extends Controller
{
    public function store(StoreMemoryRequest $request): JsonResponse
    {
        $user = $request->user();
        $couple = Couple::findOrFail($user->active_couple_id);
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

        $answerB = $request->string('answer_b')->toString();

        $memory = SaveMemoryFromAnswerAction::run(
            $session,
            $question,
            $user,
            $couple,
            $request->string('answer_a')->toString(),
            $answerB !== '' ? $answerB : null,
            $request->date('answered_at') ?? now(),
        );

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
        $paginator = Couple::findOrFail($request->user()->active_couple_id)->memories()
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
