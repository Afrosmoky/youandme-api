<?php

use App\Modules\Catalog\Support\SessionQuestionPool;
use App\Modules\Progress\Models\ProgressMilestone;

test('the seeder loads the seven map stages in order', function (): void {
    $this->seed();

    $milestones = ProgressMilestone::query()->orderBy('ordering')->get();

    expect($milestones)->toHaveCount(7)
        ->and($milestones->pluck('threshold')->all())->toBe([5, 15, 30, 45, 60, 80, 100])
        ->and($milestones->pluck('ordering')->all())->toBe([1, 2, 3, 4, 5, 6, 7])
        ->and($milestones->first()->name)->toBe('Pierwsze iskry');
});

test('no milestone sits above the session deck, so every node can be reached', function (): void {
    $this->seed();

    // The ceiling asked for by name rather than written as 100: the map counts
    // the played set, only session cards enter it, and SessionQuestionPool is the
    // one place that says what a session card is. Ask it, and this guard tracks
    // the deck — it starts failing the day thresholds outgrow the content again,
    // which is exactly how the 365 ladder survived unnoticed.
    $deckSize = SessionQuestionPool::unseen([])->count();

    expect($deckSize)->toBeGreaterThan(0)
        ->and(ProgressMilestone::query()->max('threshold'))->toBeLessThanOrEqual($deckSize);
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
