<?php

use App\Modules\Memories\Models\Memory;
use App\Modules\Progress\Actions\CheckMilestonesAction;
use App\Modules\Progress\Models\ProgressMilestone;
use Illuminate\Support\Facades\DB;
use Youandme\Auth\Models\User;

/*
 | progress:backfill-milestones — the one-off repair for couples whose cards
 | predate the progress map.
 |
 | History is built with the factory rather than SaveMemoryAction on purpose: the
 | factory emits no MemoryCreated, which is exactly the situation being repaired —
 | memories on disk, an empty register.
 */

function unlockedCountOf(int $coupleId): int
{
    return DB::table('couple_milestone_unlocks')->where('couple_id', $coupleId)->count();
}

function giveHistory(User $user, int $cards): void
{
    Memory::factory()->count($cards)->create([
        'user_id' => $user->id,
        'couple_id' => $user->active_couple_id,
    ]);
}

test('a couple with history gets every milestone it had already earned', function (): void {
    ProgressMilestone::factory()->at(1)->create();
    ProgressMilestone::factory()->at(3)->create();
    ProgressMilestone::factory()->at(10)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    giveHistory($user, 4);

    // Before: cards played, map untouched.
    expect(unlockedCountOf($coupleId))->toBe(0);

    $this->artisan('progress:backfill-milestones')->assertSuccessful();

    // Both thresholds at or below 4, and nothing above it.
    expect(unlockedCountOf($coupleId))->toBe(2);
});

test('running it again records nothing new', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $user = createUserWithCouple();
    giveHistory($user, 2);

    $this->artisan('progress:backfill-milestones')->assertSuccessful();
    $this->artisan('progress:backfill-milestones')->assertSuccessful();

    expect(unlockedCountOf(activeCoupleOf($user)->id))->toBe(1);
});

test('a couple that has played nothing gets nothing', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $user = createUserWithCouple();

    $this->artisan('progress:backfill-milestones')->assertSuccessful();

    expect(unlockedCountOf(activeCoupleOf($user)->id))->toBe(0);
});

test('milestones already recorded are left alone and the rest are filled in', function (): void {
    ProgressMilestone::factory()->at(1)->create();
    ProgressMilestone::factory()->at(5)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    giveHistory($user, 6);

    // Partial state: one stage recorded, one missing — what a couple looks like
    // if they played once after the feature shipped but have older history.
    CheckMilestonesAction::run($coupleId, 1);
    expect(unlockedCountOf($coupleId))->toBe(1);

    $this->artisan('progress:backfill-milestones')->assertSuccessful();

    expect(unlockedCountOf($coupleId))->toBe(2);
});

test('each couple is backfilled against its own history', function (): void {
    ProgressMilestone::factory()->at(1)->create();
    ProgressMilestone::factory()->at(5)->create();

    $busy = createUserWithCouple();
    $quiet = createUserWithCouple();
    giveHistory($busy, 5);
    giveHistory($quiet, 1);

    $this->artisan('progress:backfill-milestones')->assertSuccessful();

    expect(unlockedCountOf(activeCoupleOf($busy)->id))->toBe(2)
        ->and(unlockedCountOf(activeCoupleOf($quiet)->id))->toBe(1);
});

test('deleted memories still count towards the backfill', function (): void {
    ProgressMilestone::factory()->at(2)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    giveHistory($user, 2);
    Memory::query()->where('couple_id', $coupleId)->first()->delete();

    // The command counts the same lifetime statistic as the listener, so a couple
    // cannot lose a backfilled milestone by tidying up their history.
    $this->artisan('progress:backfill-milestones')->assertSuccessful();

    expect(unlockedCountOf($coupleId))->toBe(1);
});

test('the command reports what it did', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $user = createUserWithCouple();
    giveHistory($user, 1);

    $this->artisan('progress:backfill-milestones')
        ->expectsOutputToContain('Checked 1 couples, recorded 1 milestones.')
        ->assertSuccessful();
});
