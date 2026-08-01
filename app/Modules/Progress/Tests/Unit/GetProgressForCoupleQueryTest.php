<?php

use App\Modules\Progress\Actions\CheckMilestonesAction;
use App\Modules\Progress\Models\ProgressMilestone;
use App\Modules\Progress\Queries\GetProgressForCoupleQuery;

test('every stage is returned in map order, with its own unlock state', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    ProgressMilestone::factory()->at(25)->create(['ordering' => 2]);
    ProgressMilestone::factory()->at(10)->create(['ordering' => 1]);

    CheckMilestonesAction::run($couple->id, 12);

    $state = GetProgressForCoupleQuery::run($couple->id, 12);

    expect($state->totalPlayed)->toBe(12)
        ->and(array_map(fn ($m): int => $m->threshold, $state->milestones))->toBe([10, 25])
        ->and($state->milestones[0]->unlocked)->toBeTrue()
        ->and($state->milestones[0]->unlockedAt)->not->toBeNull()
        ->and($state->milestones[1]->unlocked)->toBeFalse()
        ->and($state->milestones[1]->unlockedAt)->toBeNull();
});

test('a locked stage still carries its name', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    ProgressMilestone::factory()->at(10)->create(['name' => 'Pierwsze iskry']);

    // The map shows where the couple is going, so nothing is hidden — the client
    // dims what is out of reach.
    expect(GetProgressForCoupleQuery::run($couple->id, 0)->milestones[0]->name)->toBe('Pierwsze iskry');
});

test('the next threshold is the closest one ahead', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    ProgressMilestone::factory()->at(10)->create(['ordering' => 1]);
    ProgressMilestone::factory()->at(25)->create(['ordering' => 2]);

    expect(GetProgressForCoupleQuery::run($couple->id, 0)->nextThreshold())->toBe(10)
        ->and(GetProgressForCoupleQuery::run($couple->id, 10)->nextThreshold())->toBe(25)
        ->and(GetProgressForCoupleQuery::run($couple->id, 24)->nextThreshold())->toBe(25);
});

test('a finished map has no next threshold', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    ProgressMilestone::factory()->at(10)->create();

    expect(GetProgressForCoupleQuery::run($couple->id, 10)->nextThreshold())->toBeNull();
});

test('a stage added below the current count does not become the target', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    ProgressMilestone::factory()->at(5)->create(['ordering' => 1]);
    ProgressMilestone::factory()->at(50)->create(['ordering' => 2]);

    // Nothing has recorded the 5 yet (the next played card will), but the couple
    // has clearly passed it — pointing the map backwards would read as a target.
    $state = GetProgressForCoupleQuery::run($couple->id, 20);

    expect($state->milestones[0]->unlocked)->toBeFalse()
        ->and($state->nextThreshold())->toBe(50);
});

test('reading the map records nothing', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    ProgressMilestone::factory()->at(10)->create();

    GetProgressForCoupleQuery::run($couple->id, 999);

    // Recording belongs to the listener on the next played card — one write path.
    expect(GetProgressForCoupleQuery::run($couple->id, 999)->milestones[0]->unlocked)->toBeFalse();
});

test('another couple unlocks do not leak in', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    $stranger = activeCoupleOf(createUserWithCouple());
    ProgressMilestone::factory()->at(10)->create();

    CheckMilestonesAction::run($stranger->id, 10);

    expect(GetProgressForCoupleQuery::run($couple->id, 10)->milestones[0]->unlocked)->toBeFalse();
});

test('an empty dictionary is an empty map', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());

    $state = GetProgressForCoupleQuery::run($couple->id, 7);

    expect($state->milestones)->toBe([])
        ->and($state->nextThreshold())->toBeNull()
        ->and($state->totalPlayed)->toBe(7);
});
