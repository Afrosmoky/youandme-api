<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Data\QuestionData;
use App\Modules\Catalog\Models\Question;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Resolve a batch of internal ids to QuestionData, categories included.
 *
 * The batch counterpart of GetQuestionByIdQuery, and it exists for the same
 * reason the batch ulid resolver does: the deck endpoint hands out up to a
 * hundred cards at once, and asking for them one at a time would be a hundred
 * round trips plus a hundred more for the categories. One query, one eager load.
 *
 * Order follows the ids given, not the database — the caller built that order
 * (the pool is randomised at build time) and a resolver has no business
 * reshuffling it.
 *
 * Ids that no longer resolve are dropped rather than left as holes; questions are
 * never deleted, so in practice the result is the input, resolved.
 */
final class ListQuestionsByIdsQuery
{
    use AsAction;

    /**
     * @param  list<int>  $ids
     * @return list<QuestionData>
     */
    public function handle(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $questions = Question::query()
            ->with('category')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $resolved = [];

        foreach ($ids as $id) {
            $question = $questions->get($id);

            if ($question !== null) {
                $resolved[] = QuestionData::fromModel($question);
            }
        }

        return $resolved;
    }
}
