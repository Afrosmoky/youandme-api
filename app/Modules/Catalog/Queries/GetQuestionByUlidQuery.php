<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Data\QuestionData;
use App\Modules\Catalog\Models\Question;
use Lorisleiva\Actions\Concerns\AsAction;

final class GetQuestionByUlidQuery
{
    use AsAction;

    public function handle(string $ulid): ?QuestionData
    {
        $question = Question::query()->with('category')->where('ulid', $ulid)->first();

        return $question ? QuestionData::fromModel($question) : null;
    }
}
