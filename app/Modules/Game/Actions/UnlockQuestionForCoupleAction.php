<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Support\UnlockSource;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Grant a couple the right to play one locked question — the single write path
 * into couple_unlocked_questions, whichever grantor pays for it (a credit in P7
 * slice 1, a promo code in slice 3). Game owns the entitlement; Rewards and
 * Premium never write here (canon §1 row 2, §5).
 *
 * Returns true only when the row was actually inserted. That return value is the
 * contract the orchestrator charges on: INSERT ... ON CONFLICT DO NOTHING makes
 * the write idempotent AND race-safe without a read-then-write window, so two
 * concurrent unlocks of the same card cost exactly one credit — the loser gets
 * false and its transaction rolls back with nothing debited.
 *
 * Raw query builder rather than the belongsToMany relation on purpose:
 * syncWithoutDetaching does SELECT-then-INSERT (a race window, and a duplicate
 * key blows up on the composite PK), and it cannot report "was it new?".
 */
final class UnlockQuestionForCoupleAction
{
    use AsAction;

    public function handle(int $coupleId, int $questionId, UnlockSource $source): bool
    {
        $inserted = DB::table('couple_unlocked_questions')->insertOrIgnore([
            'couple_id' => $coupleId,
            'question_id' => $questionId,
            'unlocked_at' => now(),
            'source' => $source->value,
        ]);

        return $inserted === 1;
    }
}
