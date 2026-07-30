<?php

namespace App\Modules\Game\Actions;

use App\Modules\Catalog\Queries\ListLockedQuestionIdsQuery;
use App\Modules\Game\Support\UnlockSource;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Open the whole closed deck for a couple — what a promo code buys.
 *
 * Game asks Catalog which questions are locked (an edge that already exists), so
 * the grantor never has to know: Premium validates the code, Game grants the
 * entitlement, and neither learns about the other (canon §5).
 *
 * Returns how many cards were NEW. Idempotent by the same ON CONFLICT DO NOTHING
 * as the single unlock, which matters here because a couple that already bought a
 * few cards with credits must keep them exactly once — the earlier rows stay as
 * they are, source and all, and only the missing ones are added.
 */
final class UnlockAllLockedQuestionsForCoupleAction
{
    use AsAction;

    public function handle(int $coupleId, UnlockSource $source): int
    {
        $lockedIds = ListLockedQuestionIdsQuery::run();

        if ($lockedIds === []) {
            return 0;
        }

        $now = now();

        $rows = array_map(fn (int $questionId): array => [
            'couple_id' => $coupleId,
            'question_id' => $questionId,
            'unlocked_at' => $now,
            'source' => $source->value,
        ], $lockedIds);

        // One statement for the whole deck: 40 rows is a single insert, and the
        // conflict clause makes re-running it a no-op instead of a duplicate-key
        // failure.
        return DB::table('couple_unlocked_questions')->insertOrIgnore($rows);
    }
}
