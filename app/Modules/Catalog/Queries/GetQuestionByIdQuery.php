<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Data\QuestionData;
use App\Modules\Catalog\Models\Question;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Internal id → QuestionData. Game holds question ids in a session's
 * remaining_ids (number[]); this resolves one back to its public contract.
 */
class GetQuestionByIdQuery
{
    use AsAction;

    public function handle(int $id): ?QuestionData
    {
        $question = Question::query()->with('category')->find($id);

        return $question ? QuestionData::fromModel($question) : null;
    }
}
