<?php

namespace App\Modules\Game\Actions;

use App\Modules\Catalog\Queries\GetQuestionIdByUlidQuery;
use App\Modules\Game\Models\Couple;
use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A couple un-likes a question (toggle off). Resolves the ulid through Catalog,
 * then detaches — idempotent: detaching a like that was never there is a no-op.
 * Unknown ulid → 404 (same contract as LikeQuestionAction).
 *
 * No entitlement guard, unlike its counterpart: taking a heart back gives the
 * couple nothing, and rows written before that guard existed have to remain
 * removable.
 */
final class UnlikeQuestionAction
{
    use AsAction;

    public function handle(Couple $couple, string $questionUlid): void
    {
        $questionId = GetQuestionIdByUlidQuery::run($questionUlid);

        if ($questionId === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $couple->likedQuestions()->detach($questionId);
    }
}
