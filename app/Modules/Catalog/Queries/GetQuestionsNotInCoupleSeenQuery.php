<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Data\QuestionData;
use App\Modules\Catalog\Models\Question;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Questions whose id is not in the given set, optionally filtered by category,
 * optionally limited.
 *
 * Module boundary (doc §2.2): the input is a list of already-seen question ids,
 * NOT a coupleId. Catalog does not know about couples and never touches the
 * couple_question_seen table (that is Game's). Game (Etap 4) resolves the seen
 * ids from its own pivot and passes them in.
 */
class GetQuestionsNotInCoupleSeenQuery
{
    use AsAction;

    /**
     * @param  list<int>  $seenQuestionIds
     * @return list<QuestionData>
     */
    public function handle(array $seenQuestionIds, ?string $categorySlug = null, ?int $limit = null): array
    {
        $query = Question::query()->with('category');

        if ($seenQuestionIds !== []) {
            $query->whereNotIn('id', $seenQuestionIds);
        }

        if ($categorySlug !== null) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()
            ->map(fn (Question $question): QuestionData => QuestionData::fromModel($question))
            ->all();
    }
}
