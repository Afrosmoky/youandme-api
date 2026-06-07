<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sessions\StartSessionRequest;
use App\Http\Resources\SessionResource;
use App\Models\Category;
use App\Models\GameSession;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    /**
     * Number of questions drawn into a fresh session (hardcoded for P3, see
     * docs/third-slice.md section 11.2).
     */
    private const POOL_SIZE = 20;

    public function start(StartSessionRequest $request): JsonResponse
    {
        $couple = $request->user()->activeCouple;

        $active = $couple->gameSessions()->active()->first();
        if ($active !== null) {
            // One active session per couple; hand back the existing one.
            return response()->json(['session' => new SessionResource($active)], Response::HTTP_CONFLICT);
        }

        $categorySlug = $request->input('category_slug');
        $category = $categorySlug !== null
            ? Category::where('slug', $categorySlug)->first()
            : null;

        $seenIds = $couple->seenQuestions()->pluck('questions.id')->all();

        $query = Question::query()
            ->whereNotIn('id', $seenIds)
            ->where('type', 'session')
            ->where('locale', 'pl');

        if ($category !== null) {
            $query->where('category_id', $category->id);
        }

        /** @var list<int> $poolIds */
        $poolIds = $query->inRandomOrder()->limit(self::POOL_SIZE)->pluck('id')->all();

        if ($poolIds === []) {
            return response()->json([
                'message' => 'Pula pytań w tej kategorii została wyczerpana. Spróbuj innej kategorii lub trybu mix.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $session = GameSession::create([
            'couple_id' => $couple->id,
            'category_id' => $category?->id,
            'mode' => 'local',
            'state' => [
                'remaining_ids' => $poolIds,
                'current_index' => 0,
                'draft_answer' => '',
            ],
            'started_at' => now(),
        ]);

        return response()->json(['session' => new SessionResource($session)], Response::HTTP_CREATED);
    }

    public function active(Request $request): JsonResponse
    {
        $session = $request->user()->activeCouple->gameSessions()->active()->first();

        if ($session === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json(['session' => new SessionResource($session)]);
    }

    public function end(Request $request, GameSession $session): Response
    {
        $this->authorizeCouple($request, $session);

        if ($session->ended_at !== null) {
            abort(Response::HTTP_GONE, 'Sesja już zakończona.');
        }

        $session->ended_at = now();
        $session->save();

        return response()->noContent();
    }

    public function skipCurrent(Request $request, GameSession $session): JsonResponse
    {
        $this->authorizeCouple($request, $session);

        if ($session->ended_at !== null) {
            abort(Response::HTTP_GONE, 'Sesja już zakończona.');
        }

        $state = $session->state;
        /** @var list<int> $remainingIds */
        $remainingIds = $state['remaining_ids'] ?? [];
        $currentIndex = (int) ($state['current_index'] ?? 0);

        if ($currentIndex >= count($remainingIds)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Sesja już wyczerpana, nie ma czego pomijać.');
        }

        $questionId = $remainingIds[$currentIndex];

        DB::transaction(function () use ($session, $state, $currentIndex, $questionId): void {
            // Skip marks the question as seen forever — no recycling back into the pool.
            // syncWithoutDetaching keeps it idempotent on the composite PK.
            $session->couple->seenQuestions()->syncWithoutDetaching([
                $questionId => ['seen_at' => now()],
            ]);

            $state['current_index'] = $currentIndex + 1;
            $session->state = $state;
            $session->cards_drawn_count++;
            $session->save();
        });

        return response()->json(['session' => new SessionResource($session)]);
    }

    /**
     * Controller-level authorization (no policies in P3 — deliberate deviation).
     */
    private function authorizeCouple(Request $request, GameSession $session): void
    {
        if ($session->couple_id !== $request->user()->active_couple_id) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }
}
