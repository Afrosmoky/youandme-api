<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Question;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Resolve a question ulid to its internal id, for other modules that hold a
 * question FK (e.g. memories.question_id). Physical-FK layer (DR-009); logical
 * reads use QuestionData via GetQuestionByUlidQuery.
 */
final class GetQuestionIdByUlidQuery
{
    use AsAction;

    public function handle(string $ulid): ?int
    {
        return Question::query()->where('ulid', $ulid)->value('id');
    }
}
