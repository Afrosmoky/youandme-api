<?php

namespace App\Modules\Game\Queries;

use App\Modules\Game\Models\Couple;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The cards this couple may not be dealt again yet — the anti-repeat half of
 * couple_question_seen, bounded by the couple's last deck reset.
 *
 * The table has two readers that must never be confused. The deal (GET
 * /questions/deck, POST /sessions/start) asks "what was played in THIS deck", and
 * reads it here. The progress map asks "what was ever played", and reads
 * CountPlayedCardsForCoupleQuery, which deliberately knows nothing about the
 * reset. That is why this is a separate Query rather than a filter on
 * Couple::seenQuestions(): a filtered relation would quietly shrink every future
 * count taken through it — the map included.
 *
 * Null deck_reset_at means "never reset": every row counts, exactly as before the
 * column existed. Otherwise a card belongs to the current deck when it was seen at
 * or after the reset (>=, so a card played at the reset instant — a frozen test
 * clock — is not dealt again). A card replayed after a reset stays out of the
 * next deal because MarkQuestionsPlayedAction refreshes its seen_at.
 *
 * Returns ids as values: the deck endpoint passes the same list to the pool and to
 * the exhaustion counts, which is what keeps those two in agreement.
 */
final class ListSeenQuestionIdsInCurrentDeckQuery
{
    use AsAction;

    /**
     * @return list<int>
     */
    public function handle(Couple $couple): array
    {
        $query = $couple->seenQuestions();

        if ($couple->deck_reset_at !== null) {
            $query->wherePivot('seen_at', '>=', $couple->deck_reset_at);
        }

        /** @var list<int> $ids */
        $ids = $query->pluck('questions.id')->all();

        return $ids;
    }
}
