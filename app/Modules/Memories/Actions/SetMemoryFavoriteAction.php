<?php

namespace App\Modules\Memories\Actions;

use App\Modules\Memories\Models\Memory;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Heart or un-heart a memory. One Action for both directions, because the caller
 * states the target state rather than asking for a flip — the two REST verbs on
 * the endpoint are then idempotent, and a retried request cannot silently undo
 * what the first one did (the question-likes lesson from P5).
 *
 * Authorization is the caller's job: the couple comes from the token, and the
 * controller checks it before the model ever reaches here.
 */
final class SetMemoryFavoriteAction
{
    use AsAction;

    public function handle(Memory $memory, bool $favorite): Memory
    {
        $memory->is_favorite = $favorite;
        $memory->save();

        return $memory;
    }
}
