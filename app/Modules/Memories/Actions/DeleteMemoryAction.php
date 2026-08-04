<?php

namespace App\Modules\Memories\Actions;

use App\Modules\Memories\Models\Memory;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Remove a memory from the couple's history — a soft delete, never a hard one.
 *
 * The row has to survive the deletion because two other readers depend on it:
 * CountMemoriesForCoupleQuery counts withTrashed (a deleted memory does not
 * un-play the card, so the progress map stays monotonic — P8), and the couple may
 * legitimately ask us to restore it. What the deletion does mean is "stop showing
 * it to us": the list and the anniversary scan both read live rows only.
 */
final class DeleteMemoryAction
{
    use AsAction;

    public function handle(Memory $memory): void
    {
        $memory->delete();
    }
}
