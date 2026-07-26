<?php

use App\Modules\Rewards\Models\CoupleDailyAdReward;
use Carbon\CarbonImmutable;

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

test('rewards:prune-ad-counters deletes buckets older than the retention window and keeps recent ones', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-26 03:15:00', 'UTC'));

    $coupleId = activeCoupleOf(createUserWithCouple())->id;

    foreach (['2026-07-26', '2026-07-25', '2026-07-19', '2026-07-18', '2026-06-01'] as $day) {
        CoupleDailyAdReward::query()->create(['couple_id' => $coupleId, 'reward_date' => $day]);
    }

    $this->artisan('rewards:prune-ad-counters')->assertSuccessful();

    // Retention is 7 days, exclusive at the boundary: 2026-07-19 is exactly the
    // cutoff and survives; only strictly older days go.
    $kept = CoupleDailyAdReward::query()->orderBy('reward_date')->pluck('reward_date')
        ->map(fn ($date) => $date->toDateString())
        ->all();

    expect($kept)->toBe(['2026-07-19', '2026-07-25', '2026-07-26']);
});

test('rewards:prune-ad-counters is a no-op when nothing is old enough', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-26 03:15:00', 'UTC'));

    $coupleId = activeCoupleOf(createUserWithCouple())->id;
    CoupleDailyAdReward::query()->create(['couple_id' => $coupleId, 'reward_date' => '2026-07-26']);

    $this->artisan('rewards:prune-ad-counters')->assertSuccessful();

    expect(CoupleDailyAdReward::query()->count())->toBe(1);
});
