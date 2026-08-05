<?php

namespace App\Modules\Memories\Actions;

use App\Modules\Memories\Models\Memory;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Remove a memory from the couple's history — a soft delete, never a hard one.
 *
 * The row has to survive the deletion: the couple may legitimately ask us to
 * restore it, and the backfill reads history withTrashed (a deleted memory does
 * not un-play the card). What the deletion does mean is "stop showing it to us":
 * the list and the anniversary scan both read live rows only.
 *
 * Since P10 the progress map no longer depends on this at all — a played card
 * lives in Game's played set, so erasing the memory it produced cannot touch the
 * counter. What used to be an argument for soft delete is now simply true by
 * construction.
 */
final class DeleteMemoryAction
{
    use AsAction;

    public function handle(Memory $memory): void
    {
        $memory->delete();
    }
}
