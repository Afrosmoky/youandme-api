<?php

namespace App\Modules\Game\Queries;

use App\Modules\Catalog\Data\QuestionData;
use App\Modules\Catalog\Queries\GetQuestionByIdQuery;
use App\Modules\Game\Models\GameSession;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Resolves the question at the session's current_index through Catalog (closing
 * the Etap 2 bridge — no direct Question::find in Game). Assumes the caller has
 * checked the index is in range; returns null only if the id no longer resolves
 * (should never happen — questions are not deleted).
 */
final class GetNextQuestionInSessionQuery
{
    use AsAction;

    public function handle(GameSession $session): ?QuestionData
    {
        $state = $session->state;
        /** @var list<int> $remainingIds */
        $remainingIds = is_array($state['remaining_ids'] ?? null) ? $state['remaining_ids'] : [];
        $currentIndex = isset($state['current_index']) ? (int) $state['current_index'] : 0;

        if ($currentIndex >= count($remainingIds)) {
            return null;
        }

        return GetQuestionByIdQuery::run((int) $remainingIds[$currentIndex]);
    }
}
