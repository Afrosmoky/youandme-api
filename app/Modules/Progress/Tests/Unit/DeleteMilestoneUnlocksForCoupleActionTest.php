<?php

use App\Modules\Progress\Actions\DeleteMilestoneUnlocksForCoupleAction;
use App\Modules\Progress\Models\ProgressMilestone;
use Illuminate\Support\Facades\DB;

test('it forgets the couple milestones and keeps the milestones themselves', function (): void {
    $user = createUserWithCouple();
    seedAccountFootprint($user);
    $other = createUserWithCouple();
    seedAccountFootprint($other);
    $milestones = ProgressMilestone::query()->count();

    DeleteMilestoneUnlocksForCoupleAction::run($user->active_couple_id);

    expect(DB::table('couple_milestone_unlocks')->where('couple_id', $user->active_couple_id)->count())->toBe(0)
        ->and(DB::table('couple_milestone_unlocks')->where('couple_id', $other->active_couple_id)->count())->toBe(1)
        ->and(ProgressMilestone::query()->count())->toBe($milestones);
});
