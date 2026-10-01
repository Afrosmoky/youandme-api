<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Question;
use App\Modules\Catalog\Support\SessionQuestionLock;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Resolve a batch of question ulids for the bulk unlock: id plus "is it locked",
 * in one statement instead of GetQuestionIdByUlidQuery + IsQuestionLockedQuery per
 * card.
 *
 * Only session cards (type='session', locale='pl') resolve — the same scope as
 * the closed deck (ListLockedQuestionsQuery), so a daily card or a foreign-locale
 * question can never be bought. Ulids outside that scope are simply absent from
 * the result; the caller decides what that means (a 422 for the unlock).
 *
 * Free cards are returned too, flagged unlocked=false: "it is no longer for sale"
 * is a different answer from "no such card", and the caller reports it as such.
 */
final class GetSessionQuestionLocksByUlidsQuery
{
    use AsAction;

    /**
     * @param  list<string>  $ulids
     * @return array<string, SessionQuestionLock> keyed by ulid
     */
    public function handle(array $ulids): array
    {
        if ($ulids === []) {
            return [];
        }

        return Question::query()
            ->whereIn('ulid', $ulids)
            ->where('type', 'session')
            ->where('locale', 'pl')
            ->get(['id', 'ulid', 'is_locked'])
            ->mapWithKeys(fn (Question $question): array => [
                $question->ulid => new SessionQuestionLock(
                    ulid: $question->ulid,
                    id: $question->id,
                    isLocked: (bool) $question->is_locked,
                ),
            ])
            ->all();
    }
}
