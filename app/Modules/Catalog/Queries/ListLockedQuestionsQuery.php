<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Data\QuestionData;
use App\Modules\Catalog\Models\Question;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The closed part of the session deck, in a stable order (by id) — the 40 cards a
 * couple can buy. Drives the deck screen (Game) and the bulk unlock of a promo
 * code (Premium path, P7 slice 3).
 *
 * Returns full QuestionData because that is Catalog's public contract for a
 * question; whether the body is shown is the caller's call. The deck endpoint
 * deliberately does NOT render it — a locked card must not leak its content
 * before it is paid for.
 */
final class ListLockedQuestionsQuery
{
    use AsAction;

    /**
     * @return list<QuestionData>
     */
    public function handle(): array
    {
        return Question::query()
            ->with('category')
            ->where('type', 'session')
            ->where('locale', 'pl')
            ->where('is_locked', true)
            ->orderBy('id')
            ->get()
            ->map(fn (Question $question): QuestionData => QuestionData::fromModel($question))
            ->all();
    }
}
