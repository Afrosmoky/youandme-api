<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Support\UnlockSource;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Grant a couple several locked questions at once — the bulk sibling of
 * UnlockQuestionForCoupleAction, with the same contract: it reports exactly what
 * was NEW, and the orchestrator charges for that and nothing else.
 *
 * One INSERT ... ON CONFLICT DO NOTHING RETURNING question_id. RETURNING is the
 * point: counting "owned before" with a separate SELECT leaves a window in which a
 * concurrent unlock lands, and the couple would be charged for a card it got from
 * the other request. RETURNING answers "what did THIS statement insert", so a card
 * is paid for at most once, whichever request wins it.
 *
 * Rows go in ascending question_id order. Two bulk unlocks over overlapping cards
 * then take the row locks in the same order and queue instead of deadlocking.
 * (Postgres inserts a VALUES list in the order given.)
 */
final class UnlockQuestionsForCoupleAction
{
    use AsAction;

    /**
     * @param  list<int>  $questionIds
     * @return list<int> ids of the questions newly unlocked, ascending
     */
    public function handle(int $coupleId, array $questionIds, UnlockSource $source): array
    {
        $questionIds = array_values(array_unique($questionIds));
        sort($questionIds);

        if ($questionIds === []) {
            return [];
        }

        $now = now();
        $placeholders = implode(', ', array_fill(0, count($questionIds), '(?, ?, ?, ?)'));
        $bindings = [];

        foreach ($questionIds as $questionId) {
            array_push($bindings, $coupleId, $questionId, $now, $source->value);
        }

        $rows = DB::select(
            "INSERT INTO couple_unlocked_questions (couple_id, question_id, unlocked_at, source)
             VALUES {$placeholders}
             ON CONFLICT DO NOTHING
             RETURNING question_id",
            $bindings,
        );

        $inserted = array_map(fn (object $row): int => (int) $row->question_id, $rows);
        sort($inserted);

        return $inserted;
    }
}
