<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Support\SessionQuestionPool;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * How many unseen cards are still behind the lock for this couple — the size of
 * the unlock funnel when a deck comes back empty (S4a).
 *
 * Deck-wide on purpose, with no category filter: an unlock opens a card for every
 * mode the couple plays, so "there is something to unlock" is a claim about the
 * whole deck, not about the category they happened to ask for.
 *
 * The complement of SessionQuestionPool::playable over the same unseen set:
 * locked AND not unlocked by this couple. One aggregate, no rows.
 */
final class CountLockedUnavailableQuestionsQuery
{
    use AsAction;

    /**
     * @param  list<int>  $seenQuestionIds
     * @param  list<int>  $unlockedQuestionIds
     */
    public function handle(array $seenQuestionIds, array $unlockedQuestionIds): int
    {
        $query = SessionQuestionPool::unseen($seenQuestionIds)
            ->where('is_locked', true);

        if ($unlockedQuestionIds !== []) {
            $query->whereNotIn('id', $unlockedQuestionIds);
        }

        return $query->count();
    }
}
