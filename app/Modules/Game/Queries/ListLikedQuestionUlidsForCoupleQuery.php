<?php

namespace App\Modules\Game\Queries;

use App\Modules\Game\Models\Couple;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Which of these questions has the couple liked — the batch counterpart of
 * IsQuestionLikedByCoupleQuery, and it exists for the same reason the batch
 * question resolver does: the deck hands out up to a hundred cards at once, and
 * asking card by card would be a hundred existence checks.
 *
 * Keyed on ulid, not internal id, unlike the single-card query. A caller serving
 * one card already holds its id (remaining_ids[current_index], the resolved
 * daily card); a caller serving a whole deck holds QuestionData, which exposes
 * only the public ulid. So this one joins questions and answers in the same
 * currency it was asked in — one query, whatever the deck size.
 *
 * Returns the liked subset of the ulids given, in no particular order: callers
 * flip it into a lookup set.
 */
final class ListLikedQuestionUlidsForCoupleQuery
{
    use AsAction;

    /**
     * @param  list<string>  $questionUlids
     * @return list<string>
     */
    public function handle(Couple $couple, array $questionUlids): array
    {
        if ($questionUlids === []) {
            return [];
        }

        /** @var list<string> $liked */
        $liked = $couple->likedQuestions()
            ->whereIn('questions.ulid', $questionUlids)
            ->pluck('questions.ulid')
            ->all();

        return $liked;
    }
}
