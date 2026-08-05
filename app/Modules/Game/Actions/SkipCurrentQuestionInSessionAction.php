<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Models\GameSession;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Skip the current card: mark the question played (seen forever, no recycling)
 * and advance current_index. Caller guards against an ended/exhausted session.
 */
final class SkipCurrentQuestionInSessionAction
{
    use AsAction;

    public function handle(GameSession $session): void
    {
        $state = $session->state;
        /** @var list<int> $remainingIds */
        $remainingIds = is_array($state['remaining_ids'] ?? null) ? $state['remaining_ids'] : [];
        $currentIndex = isset($state['current_index']) ? (int) $state['current_index'] : 0;
        $questionId = $remainingIds[$currentIndex];

        DB::transaction(function () use ($session, $state, $currentIndex, $questionId): void {
            // Single write path (P10) — idempotent on the composite PK. A skipped
            // card still counts as played: the couple saw it and moved on, which
            // is exactly the definition the progress map uses.
            MarkQuestionsPlayedAction::run($session->couple, [$questionId]);

            $state['current_index'] = $currentIndex + 1;
            $session->state = $state;
            $session->cards_drawn_count++;
            $session->save();
        });
    }
}
