<?php

namespace App\Modules\Progress\Queries;

use App\Modules\Progress\Models\ProgressMilestone;
use App\Modules\Progress\Support\MilestoneState;
use App\Modules\Progress\Support\ProgressState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The progress map for one couple: the whole dictionary in map order, each stage
 * marked with whether this couple has reached it.
 *
 * totalPlayed arrives as a plain value — Progress does not count cards, Memories
 * does, and the app layer joins them. That is the same discipline as
 * CheckMilestonesAction, and what keeps this module a leaf.
 *
 * Pure read (CQS): looking at the map never records a milestone, even if the
 * count says one is due. Recording is the listener's job on the next played card,
 * which keeps a single write path into the register.
 */
final class GetProgressForCoupleQuery
{
    use AsAction;

    public function handle(int $coupleId, int $totalPlayed): ProgressState
    {
        $unlockedAt = DB::table('couple_milestone_unlocks')
            ->where('couple_id', $coupleId)
            ->pluck('unlocked_at', 'milestone_id');

        $milestones = ProgressMilestone::query()
            ->orderBy('ordering')
            ->get()
            ->map(function (ProgressMilestone $milestone) use ($unlockedAt): MilestoneState {
                $reachedAt = $unlockedAt[$milestone->id] ?? null;

                return new MilestoneState(
                    slug: $milestone->slug,
                    name: $milestone->name,
                    threshold: $milestone->threshold,
                    ordering: $milestone->ordering,
                    unlocked: $reachedAt !== null,
                    unlockedAt: $reachedAt !== null ? CarbonImmutable::parse($reachedAt) : null,
                );
            })
            ->all();

        return new ProgressState(totalPlayed: $totalPlayed, milestones: $milestones);
    }
}
