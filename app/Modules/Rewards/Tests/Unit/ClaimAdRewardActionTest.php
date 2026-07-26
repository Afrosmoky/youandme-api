<?php

use App\Modules\Rewards\Actions\ClaimAdRewardAction;
use App\Modules\Rewards\Models\CoupleDailyAdReward;
use Carbon\CarbonImmutable;

/*
 | The Action takes the couple's local day as a parameter, so these run without
 | touching the clock. Cap and credits mirror the product constants (5 × 1).
 */

test('ClaimAdRewardAction grants credits and opens the bucket on the first claim', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    $day = CarbonImmutable::parse('2026-07-26', 'UTC');

    $result = ClaimAdRewardAction::run($coupleId, $day);

    expect($result->granted)->toBeTrue()
        ->and($result->creditsAwarded)->toBe(1)
        ->and($result->remainingToday)->toBe(4)
        ->and(creditsOfCouple($coupleId))->toBe(1);
});

test('ClaimAdRewardAction stops granting at the daily cap', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    $day = CarbonImmutable::parse('2026-07-26', 'UTC');

    foreach (range(1, 5) as $ignored) {
        ClaimAdRewardAction::run($coupleId, $day);
    }

    $result = ClaimAdRewardAction::run($coupleId, $day);

    expect($result->granted)->toBeFalse()
        ->and($result->creditsAwarded)->toBe(0)
        ->and($result->remainingToday)->toBe(0)
        ->and(creditsOfCouple($coupleId))->toBe(5)
        ->and(CoupleDailyAdReward::query()->where('couple_id', $coupleId)->firstOrFail()->count)->toBe(5);
});

test('ClaimAdRewardAction counts each local day separately', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    foreach (range(1, 5) as $ignored) {
        ClaimAdRewardAction::run($coupleId, CarbonImmutable::parse('2026-07-26', 'UTC'));
    }

    $result = ClaimAdRewardAction::run($coupleId, CarbonImmutable::parse('2026-07-27', 'UTC'));

    expect($result->granted)->toBeTrue()
        ->and($result->remainingToday)->toBe(4)
        ->and(creditsOfCouple($coupleId))->toBe(6)
        ->and(CoupleDailyAdReward::query()->where('couple_id', $coupleId)->count())->toBe(2);
});

test('ClaimAdRewardAction keeps a couple\'s counters independent from another couple\'s', function (): void {
    $ours = activeCoupleOf(createUserWithCouple())->id;
    $theirs = activeCoupleOf(createUserWithCouple())->id;
    $day = CarbonImmutable::parse('2026-07-26', 'UTC');

    foreach (range(1, 5) as $ignored) {
        ClaimAdRewardAction::run($ours, $day);
    }

    $result = ClaimAdRewardAction::run($theirs, $day);

    expect($result->granted)->toBeTrue()
        ->and(creditsOfCouple($theirs))->toBe(1)
        ->and(creditsOfCouple($ours))->toBe(5);
});

test('ClaimAdRewardAction never lets the counter pass the cap (conditional increment)', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    $day = CarbonImmutable::parse('2026-07-26', 'UTC');

    foreach (range(1, 12) as $ignored) {
        ClaimAdRewardAction::run($coupleId, $day);
    }

    // The counter is the cap enforcement — it must never exceed the cap, no matter
    // how many claims arrive (this is the "affected == 1" guard, not a read-check).
    expect(CoupleDailyAdReward::query()->where('couple_id', $coupleId)->firstOrFail()->count)->toBe(5)
        ->and(creditsOfCouple($coupleId))->toBe(5);
});
