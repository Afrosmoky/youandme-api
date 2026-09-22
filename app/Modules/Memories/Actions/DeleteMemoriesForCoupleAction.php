<?php

namespace App\Modules\Memories\Actions;

use App\Modules\Memories\Models\Memory;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Erase a couple's whole history — the account-deletion half that Memories owns.
 *
 * A hard delete, and withTrashed on purpose: a memory the couple removed earlier
 * is still a row with their answers in it (DeleteMemoryAction only hides it), and
 * deleting an account has to take those too. The opposite of the everyday soft
 * delete, which exists precisely so a single removal can be undone.
 *
 * Runs before Game drops the couple's sessions: memories.game_session_id points
 * at game_sessions without a cascade.
 */
final class DeleteMemoriesForCoupleAction
{
    use AsAction;

    public function handle(int $coupleId): void
    {
        Memory::withTrashed()->where('couple_id', $coupleId)->forceDelete();
    }
}
