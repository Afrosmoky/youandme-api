<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Question;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one place that says what a session card IS and which of them a couple may
 * still be dealt. Every read that has to agree with the deck starts here:
 * GetSessionQuestionPoolQuery (what gets dealt) and the two exhaustion counts
 * (why nothing got dealt).
 *
 * Shared on purpose (S4a): the exhaustion reason is a claim ABOUT the pool, so a
 * filter that drifts from the pool's own would make the endpoint lie — "unlock
 * something" while free cards wait, or "you finished the deck" while they do.
 * One builder, one truth.
 *
 * Module boundary is unchanged (doc §2.2): the seen and unlocked sets arrive as
 * lists of ids from Game, Catalog never reads couple_* tables itself.
 */
final class SessionQuestionPool
{
    /**
     * Session cards this couple has not been dealt yet, whether or not they may
     * play them.
     *
     * @param  list<int>  $seenQuestionIds
     * @return Builder<Question>
     */
    public static function unseen(array $seenQuestionIds): Builder
    {
        $query = Question::query()
            ->where('type', 'session')
            ->where('locale', 'pl');

        if ($seenQuestionIds !== []) {
            $query->whereNotIn('id', $seenQuestionIds);
        }

        return $query;
    }

    /**
     * The unseen cards this couple may actually play: free ones plus the locked
     * ones it unlocked (P7 closed deck).
     *
     * @param  list<int>  $seenQuestionIds
     * @param  list<int>  $unlockedQuestionIds
     * @return Builder<Question>
     */
    public static function playable(array $seenQuestionIds, array $unlockedQuestionIds): Builder
    {
        return self::unseen($seenQuestionIds)
            ->where(function (Builder $q) use ($unlockedQuestionIds): void {
                $q->where('is_locked', false);

                if ($unlockedQuestionIds !== []) {
                    $q->orWhereIn('id', $unlockedQuestionIds);
                }
            });
    }
}
