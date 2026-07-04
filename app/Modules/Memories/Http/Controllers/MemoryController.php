<?php

namespace App\Modules\Memories\Http\Controllers;

use App\Modules\Memories\Http\Resources\MemoryResource;
use App\Modules\Memories\Models\Memory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pure Memories read endpoint. Resolves the couple id from the Auth user's
 * active_couple_id (a plain int) and filters Memories' own table — no Game
 * import, so Memories does not depend on Game. POST /memories (the session-answer
 * save) lives in the app layer (App\Http\Controllers\Api\V1\MemoryController).
 */
class MemoryController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 50));

        // id is the cursor tiebreaker so memories sharing an answered_at don't
        // get skipped across pages.
        $paginator = Memory::query()
            ->where('couple_id', $request->user()->active_couple_id)
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
