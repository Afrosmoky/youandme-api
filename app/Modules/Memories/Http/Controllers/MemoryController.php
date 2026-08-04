<?php

namespace App\Modules\Memories\Http\Controllers;

use App\Modules\Memories\Actions\DeleteMemoryAction;
use App\Modules\Memories\Actions\SetMemoryFavoriteAction;
use App\Modules\Memories\Actions\UpdateMemoryAnswersAction;
use App\Modules\Memories\Http\Requests\UpdateMemoryRequest;
use App\Modules\Memories\Http\Resources\MemoryResource;
use App\Modules\Memories\Models\Memory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Pure Memories endpoints: the couple's own history — list it, heart it, edit it,
 * remove it. The couple id comes from the Auth user's active_couple_id (a plain
 * int), and every write is checked against it, so a valid ulid from another
 * couple's history is a 403 rather than a way in.
 *
 * No Game import, so Memories does not depend on Game. POST /memories (the
 * session-answer save, which peer-combines memory + session) stays in the app
 * layer (App\Http\Controllers\Api\V1\MemoryController).
 */
final class MemoryController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 50));

        // id is the cursor tiebreaker so memories sharing an answered_at don't
        // get skipped across pages.
        $paginator = Memory::query()
            ->where('couple_id', $request->user()->active_couple_id)
            // ?favorites=1 narrows the same list rather than adding a second
            // endpoint: the shape, the order and the cursor stay identical, which
            // is what lets the client reuse one screen with a filter on top.
            ->when($request->boolean('favorites'), fn ($query) => $query->where('is_favorite', true))
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

    /**
     * One memory by ulid — what the card screen opens on, whether the client
     * reached it from the list or from an anniversary push. A deep link points at
     * a memory a month or a year old, which is nowhere near the first cursor page,
     * so the client cannot be expected to hold it already.
     *
     * Resolved by route-model binding rather than through GetMemoryByUlidQuery:
     * the Query answers with MemoryData, which deliberately carries no couple id,
     * so it cannot authorize — and the response has to be the same MemoryResource
     * the list serializes. The Query stays what it always was, the read Public API
     * for other modules.
     */
    public function show(Request $request, Memory $memory): JsonResponse
    {
        $this->authorizeCouple($request, $memory);

        return response()->json(['memory' => new MemoryResource($memory->load('question.category'))]);
    }

    public function update(UpdateMemoryRequest $request, Memory $memory): JsonResponse
    {
        $this->authorizeCouple($request, $memory);

        $answerB = $request->string('answer_b')->toString();

        UpdateMemoryAnswersAction::run(
            $memory,
            $request->string('answer_a')->toString(),
            $answerB !== '' ? $answerB : null,
        );

        return response()->json(['memory' => new MemoryResource($memory->load('question.category'))]);
    }

    public function favorite(Request $request, Memory $memory): JsonResponse
    {
        return $this->setFavorite($request, $memory, true);
    }

    public function unfavorite(Request $request, Memory $memory): JsonResponse
    {
        return $this->setFavorite($request, $memory, false);
    }

    public function destroy(Request $request, Memory $memory): Response
    {
        $this->authorizeCouple($request, $memory);

        DeleteMemoryAction::run($memory);

        return response()->noContent();
    }

    private function setFavorite(Request $request, Memory $memory, bool $favorite): JsonResponse
    {
        $this->authorizeCouple($request, $memory);

        SetMemoryFavoriteAction::run($memory, $favorite);

        return response()->json(['memory' => new MemoryResource($memory->load('question.category'))]);
    }

    /**
     * Controller-level authorization (no policies — the deliberate P3 deviation,
     * same as SessionController). Reads the authenticated Auth user's
     * active_couple_id: the Memories↔Auth seam.
     *
     * Route-model binding resolves {memory:ulid} through the SoftDeletes scope, so
     * an already-deleted memory is a 404 here and never reaches this check.
     */
    private function authorizeCouple(Request $request, Memory $memory): void
    {
        if ($memory->couple_id !== $request->user()->active_couple_id) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }
}
