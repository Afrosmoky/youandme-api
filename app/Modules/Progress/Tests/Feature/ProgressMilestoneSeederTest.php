<?php

use App\Modules\Progress\Models\ProgressMilestone;

test('the seeder loads the seven map stages in order', function (): void {
    $this->seed();

    $milestones = ProgressMilestone::query()->orderBy('ordering')->get();

    expect($milestones)->toHaveCount(7)
        ->and($milestones->pluck('threshold')->all())->toBe([10, 25, 50, 100, 200, 300, 365])
        ->and($milestones->pluck('ordering')->all())->toBe([1, 2, 3, 4, 5, 6, 7])
        ->and($milestones->first()->name)->toBe('Pierwsze iskry');
});

test('re-seeding is idempotent', function (): void {
    $this->seed();
    $this->seed();

    expect(ProgressMilestone::query()->count())->toBe(7);
});

test('thresholds are unique, so "the next milestone" is never ambiguous', function (): void {
    $this->seed();

    expect(ProgressMilestone::query()->distinct()->count('threshold'))->toBe(7);
});
