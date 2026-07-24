<?php

namespace App\Modules\Game\Actions;

use App\Modules\Catalog\Queries\GetQuestionIdByUlidQuery;
use App\Modules\Game\Models\Couple;
use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A couple likes a question. Resolves the question ulid to its id through
 * Catalog (Public API — Game does not load Question with Eloquent), then records
 * the like idempotently: syncWithoutDetaching skips a second like on the same
 * composite PK without erroring (same pattern as couple_question_seen). Unknown
 * ulid → 404.
 */
final class LikeQuestionAction
{
    use AsAction;

    public function handle(Couple $couple, string $questionUlid): void
    {
        $questionId = GetQuestionIdByUlidQuery::run($questionUlid);

        if ($questionId === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $couple->likedQuestions()->syncWithoutDetaching([$questionId => ['liked_at' => now()]]);
    }
}
