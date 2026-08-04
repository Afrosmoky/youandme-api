<?php

namespace App\Modules\Memories\Actions;

use App\Modules\Memories\Models\Memory;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Rewrite the answers of an existing memory.
 *
 * Only the two answers move. The name snapshots (player_a_name/player_b_name)
 * stay exactly as they were written, because they record who played the card back
 * then — editing a typo years later must not rewrite that history (P3 pattern 8).
 * answered_at stays too: the memory still happened when it happened; only what was
 * said about it changes. updated_at records the edit.
 *
 * No event: a corrected answer is not a played card, so nothing downstream
 * (progress, streaks) may react to it.
 */
final class UpdateMemoryAnswersAction
{
    use AsAction;

    public function handle(Memory $memory, string $answerA, ?string $answerB): Memory
    {
        $memory->answer_a = $answerA;
        $memory->answer_b = $answerB;
        $memory->save();

        return $memory;
    }
}
