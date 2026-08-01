<?php

namespace App\Listeners;

use App\Modules\Memories\Events\MemoryCreated;
use App\Modules\Memories\Queries\CountMemoriesForCoupleQuery;
use App\Modules\Progress\Actions\CheckMilestonesAction;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Composition-root wiring: a played card advances the progress map. The first
 * real consumer of a domain event in this project — events have been emitted
 * since day one precisely so this moment needed no rebuild.
 *
 * Why an event here, when P7's unlock and redeem were commands: the caller needs
 * nothing back. Saving an answer neither waits for the milestone check nor cares
 * how it went, which is the definition of a decoupled side effect. Unlock and
 * redeem were the opposite — the response depended on their result, so they had
 * to be synchronous commands in one transaction.
 *
 * The listener is the only place that knows both modules: Memories counts,
 * Progress decides. Progress receives the total as a value and stays a leaf.
 *
 * Nothing here may break the save. The event fires after commit, so a throw would
 * not roll the answer back any more, but it would still surface as a failed
 * request for a card the couple actually saved. Swallowing it is safe because
 * CheckMilestonesAction records everything crossed rather than just the newest
 * step — a lost run is repaired by the next card.
 */
final class CheckMilestonesOnMemoryCreated
{
    public function handle(MemoryCreated $event): void
    {
        $coupleId = $event->data->coupleId;

        try {
            CheckMilestonesAction::run($coupleId, CountMemoriesForCoupleQuery::run($coupleId));
        } catch (Throwable $exception) {
            Log::warning('Milestone check failed for a saved memory', [
                'couple_id' => $coupleId,
                'memory_ulid' => $event->data->memoryUlid,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
