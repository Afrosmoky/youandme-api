<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Events\CardsPlayed;
use App\Modules\Game\Models\Couple;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mark cards as played by a couple — the SINGLE write path into
 * couple_question_seen (P10). Every loop that plays a question card comes through
 * here: the session answer, the session skip, and the local game (its batch
 * report, and the memory it saves).
 *
 * Note what that table means now. P3 built it as the session anti-repeat log ("a
 * question, once seen, never returns"); since P10 it is ALSO the register of
 * played cards that the progress map counts. The schema did not change, the
 * meaning did — and that is precisely why this single door exists: two meanings
 * on one table stay consistent only if nothing writes to it behind their back.
 *
 * The daily card deliberately does NOT come through here (Piotr, P10): the map
 * counts the question game — sessions and local play — while the daily card has
 * its own system, the streak. That separation is also what lets the daily loop
 * serve the same card again months later without touching anything counted here.
 *
 * Idempotent by the composite PK — syncWithoutDetaching is set semantics, so a
 * retried report costs one statement and changes nothing. Returns how many cards
 * were NEW to the set.
 */
final class MarkQuestionsPlayedAction
{
    use AsAction;

    /**
     * @param  list<int>  $questionIds  internal ids, already checked as playable
     */
    public function handle(Couple $couple, array $questionIds): int
    {
        if ($questionIds === []) {
            return 0;
        }

        $seenAt = now();

        $changes = $couple->seenQuestions()->syncWithoutDetaching(
            collect($questionIds)
                ->mapWithKeys(fn (int $questionId): array => [$questionId => ['seen_at' => $seenAt]])
                ->all()
        );

        // Announced even when nothing was new. The consumer records every
        // milestone the couple has crossed rather than just the newest one, so a
        // redundant run is cheap and repairs whatever an earlier one missed —
        // the same self-healing property CheckMilestonesAction was built with.
        CardsPlayed::dispatch($couple->id);

        return count($changes['attached']);
    }
}
