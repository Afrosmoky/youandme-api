<?php

namespace App\Modules\Game\Http\Controllers;

use App\Modules\Game\Actions\EndGameSessionAction;
use App\Modules\Game\Actions\SkipCurrentQuestionInSessionAction;
use App\Modules\Game\Actions\StartGameSessionAction;
use App\Modules\Game\Http\Requests\StartSessionRequest;
use App\Modules\Game\Http\Resources\SessionResource;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SessionController
{
    public function start(StartSessionRequest $request): JsonResponse
    {
        $couple = Couple::findOrFail($request->user()->active_couple_id);

        $active = $couple->gameSessions()->active()->first();
        if ($active !== null) {
            // One active session per couple; hand back the existing one.
            return response()->json(['session' => new SessionResource($active)], Response::HTTP_CONFLICT);
        }

        $session = StartGameSessionAction::run($couple, $request->input('category_slug'));

        if ($session === null) {
            return response()->json([
                'message' => 'Pula pytań w tej kategorii została wyczerpana. Spróbuj innej kategorii lub trybu mix.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['session' => new SessionResource($session)], Response::HTTP_CREATED);
    }

    public function active(Request $request): JsonResponse
    {
        $session = Couple::findOrFail($request->user()->active_couple_id)->gameSessions()->active()->first();

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

        EndGameSessionAction::run($session);

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
        $remainingIds = is_array($state['remaining_ids'] ?? null) ? $state['remaining_ids'] : [];
        $currentIndex = isset($state['current_index']) ? (int) $state['current_index'] : 0;

        if ($currentIndex >= count($remainingIds)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Sesja już wyczerpana, nie ma czego pomijać.');
        }

        SkipCurrentQuestionInSessionAction::run($session);

        return response()->json(['session' => new SessionResource($session)]);
    }

    /**
     * Controller-level authorization (no policies in P3 — deliberate deviation).
     * Reads the authenticated Auth user's active_couple_id — the Game↔Auth seam.
     */
    private function authorizeCouple(Request $request, GameSession $session): void
    {
        if ($session->couple_id !== $request->user()->active_couple_id) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }
}
