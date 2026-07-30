<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Question;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Is this question part of the closed deck? Catalog owns is_locked, so the
 * unlock orchestrator (app layer) asks here instead of reading the column
 * itself — "is it locked" is a Catalog fact, "did this couple unlock it" is a
 * Game fact (canon §5).
 *
 * A missing question answers false: the caller has already resolved the ulid to
 * an id (404 otherwise), so this is never how a bad id is reported.
 */
final class IsQuestionLockedQuery
{
    use AsAction;

    public function handle(int $questionId): bool
    {
        return Question::query()
            ->whereKey($questionId)
            ->where('is_locked', true)
            ->exists();
    }
}
