<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Support\SessionQuestionPool;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * How many cards this couple could still play if it asked for a different
 * category — the first question S4a asks when a deck comes back empty.
 *
 * Built on the same SessionQuestionPool::playable as the deck itself, and the
 * category clause is the exact complement of the one GetSessionQuestionPoolQuery
 * applies: whereDoesntHave, not `category_id <> ?`. questions.category_id is
 * nullable, and a card with no category is unreachable through a category
 * request, so it belongs on the "somewhere else" side of the line — `<>` would
 * silently drop it and could send a couple to the unlock funnel with free cards
 * still on the table.
 *
 * Only the count is needed (the client is told "try another category", not which
 * one), so this is one aggregate, no rows.
 */
final class CountPlayableQuestionsOutsideCategoryQuery
{
    use AsAction;

    /**
     * @param  list<int>  $seenQuestionIds
     * @param  list<int>  $unlockedQuestionIds
     */
    public function handle(array $seenQuestionIds, array $unlockedQuestionIds, string $categorySlug): int
    {
        return SessionQuestionPool::playable($seenQuestionIds, $unlockedQuestionIds)
            ->whereDoesntHave('category', fn (Builder $q) => $q->where('slug', $categorySlug))
            ->count();
    }
}
