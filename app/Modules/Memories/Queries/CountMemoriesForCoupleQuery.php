<?php

namespace App\Modules\Memories\Queries;

use App\Modules\Memories\Models\Memory;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * How many cards this couple has ever played. Public API of Memories — the
 * progress map is driven by it, and Progress receives the number as a value
 * rather than reaching into this table itself.
 *
 * Counted, not materialised: memories is the single source of truth for "a card
 * was played", so a column on couples would be a second place to keep in sync for
 * no gain at MVP scale (the likes_count lesson from P5, applied the other way).
 *
 * withTrashed on purpose. This is a LIFETIME statistic: deleting a memory removes
 * it from the couple's history, but it does not un-play the card. Counting only
 * live rows would let the number fall below a milestone the couple already holds
 * — the register of unlocks is monotonic, so the counter feeding it must be too.
 */
final class CountMemoriesForCoupleQuery
{
    use AsAction;

    public function handle(int $coupleId): int
    {
        return Memory::query()
            ->withTrashed()
            ->where('couple_id', $coupleId)
            ->count();
    }
}
