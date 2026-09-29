<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Models\Couple;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * "Zacznij od nowa" — the couple gets the full deck again (refactor-roadmap §12
 * item 19).
 *
 * Moves the start of the current deck to now and deletes nothing. The played
 * cards stay in couple_question_seen, so the progress map and its milestones do
 * not move; only the deal stops skipping them (ListSeenQuestionIdsInCurrentDeckQuery).
 * Likes, unlocked cards and credits live in their own tables and are untouched.
 *
 * Idempotent in effect: a second reset just moves the marker again. No event —
 * nothing reacts to it, and an announcement without a consumer is the debt the
 * weekly ritual already declined to add.
 *
 * A running server session keeps its frozen pool; the reset applies from the next
 * POST /sessions/start, the same rule a mid-session unlock follows.
 */
final class ResetDeckAction
{
    use AsAction;

    public function handle(Couple $couple): void
    {
        $couple->deck_reset_at = now();
        $couple->save();
    }
}
