<?php

namespace App\Modules\Game\Actions;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Write cards into the played set with the date they were actually played — the
 * one-off repair path for history that predates the set (P10 backfill).
 *
 * This is the SECOND door into couple_question_seen, and it is deliberate rather
 * than a crack in the invariant. MarkQuestionsPlayedAction is the domain door:
 * cards being played now, stamped now, announced with CardsPlayed. This one
 * reconstructs a past that was never announced — the timestamps come from the
 * memories the couple wrote, and it stays silent, because nothing "happened"
 * when a backfill catches up. The caller re-checks the milestones once per couple
 * afterwards instead, which is the whole point of running it.
 *
 * Keeping the two apart is what stops the domain door from growing a "…but with
 * these dates, and don't tell anyone" parameter that every future caller would
 * then have to reason about.
 *
 * Takes a couple id and a map, not a Couple: the caller iterates couples in
 * chunks and never needs the relation, and insertOrIgnore is one statement per
 * couple rather than one per card.
 *
 * Idempotent by the composite PK (ON CONFLICT DO NOTHING), so a re-run is free
 * and never moves a seen_at that is already recorded.
 */
final class RecordHistoricalPlayedCardsAction
{
    use AsAction;

    /**
     * @param  array<int, DateTimeInterface>  $playedAt  question id => when it was played
     * @return int how many cards were new to the set
     */
    public function handle(int $coupleId, array $playedAt): int
    {
        if ($playedAt === []) {
            return 0;
        }

        $rows = [];

        foreach ($playedAt as $questionId => $seenAt) {
            $rows[] = [
                'couple_id' => $coupleId,
                'question_id' => $questionId,
                'seen_at' => $seenAt,
            ];
        }

        return DB::table('couple_question_seen')->insertOrIgnore($rows);
    }
}
