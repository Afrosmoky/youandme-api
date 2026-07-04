<?php

namespace App\Modules\Memories\Queries;

use App\Modules\Memories\Data\MemoryData;
use App\Modules\Memories\Data\MemoryPage;
use App\Modules\Memories\Models\Memory;
use Illuminate\Pagination\Cursor;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Cursor page of a couple's memories, newest first. Input is a couple id (a
 * plain int identifier passed by the caller — Memories filters its own
 * memories.couple_id, no Game import needed). Read Public API returning MemoryPage;
 * the HTTP index serializes via MemoryResource (byte-identical) instead.
 */
final class ListMemoriesForCoupleQuery
{
    use AsAction;

    public function handle(int $coupleId, ?string $cursor, int $perPage): MemoryPage
    {
        // id is the cursor tiebreaker so memories sharing an answered_at don't
        // get skipped across pages.
        $paginator = Memory::query()
            ->where('couple_id', $coupleId)
            ->with('question.category')
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor !== null ? Cursor::fromEncoded($cursor) : null);

        return new MemoryPage(
            data: array_map(
                fn (Memory $memory): MemoryData => MemoryData::fromModel($memory),
                $paginator->items(),
            ),
            nextCursor: $paginator->nextCursor()?->encode(),
            prevCursor: $paginator->previousCursor()?->encode(),
            perPage: $paginator->perPage(),
        );
    }
}
