<?php

namespace App\Modules\Game\Queries;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * How many cards this couple has played — the size of their played set
 * (couple_question_seen). Public API of Game: the progress map is driven by this
 * number, and Progress receives it as a value rather than reaching into the table
 * itself, which is what keeps that module a leaf.
 *
 * Counted, not materialised — the P8 rule, unchanged by moving the source: the
 * set is the single source of truth for "this card was played", so a column on
 * couples would be a second place to keep in sync for no gain at MVP scale.
 *
 * Monotonic by construction: a card can only enter the set, never leave it. The
 * milestone register it feeds is monotonic too, and here that costs nothing —
 * where the count came from memories it took a deliberate withTrashed to hold.
 *
 * It deliberately does NOT know about couples.deck_reset_at. A deck reset renews
 * what the couple may be dealt (ListSeenQuestionIdsInCurrentDeckQuery), never what
 * they have played: the rows stay, and every one of them keeps counting here. Do
 * not add a seen_at filter, do not route this through a "current deck" helper,
 * and do not count through a relation someone may one day scope — any of those
 * would silently shrink the map, and with it the milestones, for every couple
 * that ever pressed "Zacznij od nowa". Pinned by DeckResetTest.
 *
 * Takes an id, not a Couple: every caller (the milestone listener, GET /progress)
 * has only the id, and hydrating a model to COUNT a pivot would be a query for
 * nothing.
 */
final class CountPlayedCardsForCoupleQuery
{
    use AsAction;

    public function handle(int $coupleId): int
    {
        return DB::table('couple_question_seen')
            ->where('couple_id', $coupleId)
            ->count();
    }
}
