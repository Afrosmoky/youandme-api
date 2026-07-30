<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Question;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Internal ids of every locked session question — what Game needs to wire the
 * entitlement rows when a promo code opens the whole deck at once.
 *
 * Separate from ListLockedQuestionsQuery (which returns QuestionData for the deck
 * screen) because the two callers want different things: one renders cards, the
 * other writes foreign keys. Same precedent as GetSessionQuestionPoolQuery
 * returning ids, not models.
 *
 * @see ListLockedQuestionsQuery
 */
final class ListLockedQuestionIdsQuery
{
    use AsAction;

    /**
     * @return list<int>
     */
    public function handle(): array
    {
        /** @var list<int> $ids */
        $ids = Question::query()
            ->where('type', 'session')
            ->where('locale', 'pl')
            ->where('is_locked', true)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        return $ids;
    }
}
