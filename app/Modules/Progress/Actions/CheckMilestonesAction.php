<?php

namespace App\Modules\Progress\Actions;

use App\Modules\Progress\Models\ProgressMilestone;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Record every milestone this couple has now reached. The single write path into
 * couple_milestone_unlocks.
 *
 * Both inputs arrive as plain values: the couple id and how many cards they have
 * played. Progress never counts anything itself — the counter lives in Memories,
 * and asking for it directly would cost this module its leaf position (canon,
 * decision 2). Same shape as deckComplete on the P7 SSV webhook.
 *
 * It writes ALL crossed-but-missing milestones, not just the one at the current
 * count, and that is what makes the whole feature self-healing: a run skipped for
 * any reason (a failed listener, an event lost before the after-commit change, a
 * threshold lowered after the fact) is repaired by the next saved card. It also
 * means a couple that arrives with history — an import, or a threshold added
 * later — catches up in one go rather than one card at a time.
 *
 * Idempotent and race-safe by the composite PK: INSERT ... ON CONFLICT DO
 * NOTHING, so running after every single card costs one statement and never
 * duplicates. Returns how many milestones were NEW, which is what a future
 * MilestoneUnlocked event (P9) would fire on.
 */
final class CheckMilestonesAction
{
    use AsAction;

    public function handle(int $coupleId, int $totalPlayed): int
    {
        $reachedIds = ProgressMilestone::query()
            ->where('threshold', '<=', $totalPlayed)
            ->pluck('id');

        if ($reachedIds->isEmpty()) {
            return 0;
        }

        $now = now();

        $rows = $reachedIds
            ->map(fn (int $milestoneId): array => [
                'couple_id' => $coupleId,
                'milestone_id' => $milestoneId,
                'unlocked_at' => $now,
            ])
            ->all();

        return DB::table('couple_milestone_unlocks')->insertOrIgnore($rows);
    }
}
