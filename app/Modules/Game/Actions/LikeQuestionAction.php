<?php

namespace App\Modules\Game\Actions;

use App\Modules\Catalog\Queries\GetQuestionIdByUlidQuery;
use App\Modules\Catalog\Queries\IsQuestionLockedQuery;
use App\Modules\Game\Exceptions\QuestionNotPlayableException;
use App\Modules\Game\Models\Couple;
use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A couple likes a question. Resolves the question ulid to its id through
 * Catalog (Public API — Game does not load Question with Eloquent), then records
 * the like idempotently: syncWithoutDetaching skips a second like on the same
 * composite PK without erroring (same pattern as couple_question_seen). Unknown
 * ulid → 404.
 *
 * A card from the closed deck that this couple has not unlocked is refused (422,
 * the module's answer for "not in your deck"). You cannot meaningfully heart a
 * card you have never seen, so a client doing it is a bug — and an exploitable
 * one, because GET /deck legitimately hands out the ulids of locked cards
 * without their body, and GET /questions/liked would then read that body back.
 * Free session cards and daily cards are unaffected: the daily deck is free
 * (P7), and hearting the daily card has worked since P5.
 *
 * Unliking has no such guard on purpose — see UnlikeQuestionAction.
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

        if (IsQuestionLockedQuery::run($questionId) && ! $this->hasUnlocked($couple, $questionId)) {
            throw new QuestionNotPlayableException;
        }

        $couple->likedQuestions()->syncWithoutDetaching([$questionId => ['liked_at' => now()]]);
    }

    private function hasUnlocked(Couple $couple, int $questionId): bool
    {
        return $couple->unlockedQuestions()->where('questions.id', $questionId)->exists();
    }
}
