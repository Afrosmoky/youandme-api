<?php

use App\Modules\Progress\Actions\CheckMilestonesAction;
use App\Modules\Progress\Models\ProgressMilestone;
use Illuminate\Support\Facades\DB;

function unlockedThresholdsOf(int $coupleId): array
{
    return DB::table('couple_milestone_unlocks')
        ->join('progress_milestones', 'progress_milestones.id', '=', 'couple_milestone_unlocks.milestone_id')
        ->where('couple_milestone_unlocks.couple_id', $coupleId)
        ->orderBy('progress_milestones.threshold')
        ->pluck('progress_milestones.threshold')
        ->all();
}

test('reaching a threshold records the milestone', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    ProgressMilestone::factory()->at(10)->create();

    expect(CheckMilestonesAction::run($couple->id, 10))->toBe(1)
        ->and(unlockedThresholdsOf($couple->id))->toBe([10]);
});

test('a count below every threshold records nothing', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    ProgressMilestone::factory()->at(10)->create();

    expect(CheckMilestonesAction::run($couple->id, 9))->toBe(0)
        ->and(unlockedThresholdsOf($couple->id))->toBe([]);
});

test('crossing several thresholds at once records them all', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    ProgressMilestone::factory()->at(10)->create();
    ProgressMilestone::factory()->at(25)->create();
    ProgressMilestone::factory()->at(50)->create();

    // A couple arriving with history — or a threshold added later — catches up in
    // one run rather than one card at a time.
    expect(CheckMilestonesAction::run($couple->id, 30))->toBe(2)
        ->and(unlockedThresholdsOf($couple->id))->toBe([10, 25]);
});

test('running again at the same count changes nothing', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    ProgressMilestone::factory()->at(10)->create();

    CheckMilestonesAction::run($couple->id, 10);

    // Idempotency is what lets this run after every single card.
    expect(CheckMilestonesAction::run($couple->id, 10))->toBe(0)
        ->and(DB::table('couple_milestone_unlocks')->count())->toBe(1);
});

test('a later card past an already-recorded milestone only adds the new one', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    ProgressMilestone::factory()->at(10)->create();
    ProgressMilestone::factory()->at(25)->create();

    CheckMilestonesAction::run($couple->id, 12);

    expect(CheckMilestonesAction::run($couple->id, 25))->toBe(1)
        ->and(unlockedThresholdsOf($couple->id))->toBe([10, 25]);
});

test('a missed run is repaired by the next card', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    ProgressMilestone::factory()->at(10)->create();
    ProgressMilestone::factory()->at(25)->create();

    // Nothing ran at 10 (a failed listener, say). The next check writes both,
    // because the action records everything crossed, not just the newest step.
    expect(CheckMilestonesAction::run($couple->id, 26))->toBe(2)
        ->and(unlockedThresholdsOf($couple->id))->toBe([10, 25]);
});

test('one couple progress does not touch another', function (): void {
    $first = activeCoupleOf(createUserWithCouple());
    $second = activeCoupleOf(createUserWithCouple());
    ProgressMilestone::factory()->at(10)->create();

    CheckMilestonesAction::run($first->id, 10);

    expect(unlockedThresholdsOf($first->id))->toBe([10])
        ->and(unlockedThresholdsOf($second->id))->toBe([]);
});

test('an empty dictionary is a no-op', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());

    expect(CheckMilestonesAction::run($couple->id, 999))->toBe(0);
});
