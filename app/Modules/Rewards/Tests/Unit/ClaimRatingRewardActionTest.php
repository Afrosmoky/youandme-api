<?php

use App\Modules\Rewards\Actions\ClaimRatingRewardAction;
use App\Modules\Rewards\Models\CoupleReward;

test('ClaimRatingRewardAction grants +5 and stamps the flag on the first claim', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    expect(creditsOfCouple($coupleId))->toBe(0);

    ClaimRatingRewardAction::run($coupleId);

    $reward = CoupleReward::query()->where('couple_id', $coupleId)->firstOrFail();
    expect($reward->credits)->toBe(5)
        ->and($reward->rating_reward_claimed_at)->not->toBeNull();
});

test('ClaimRatingRewardAction is a no-op on the second claim (no double grant)', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    ClaimRatingRewardAction::run($coupleId);
    $stamp = CoupleReward::query()->where('couple_id', $coupleId)->firstOrFail()->rating_reward_claimed_at;

    ClaimRatingRewardAction::run($coupleId);

    $reward = CoupleReward::query()->where('couple_id', $coupleId)->firstOrFail();
    expect($reward->credits)->toBe(5)
        ->and($reward->rating_reward_claimed_at->equalTo($stamp))->toBeTrue();
});

test('ClaimRatingRewardAction creates the missing reward account instead of silently skipping the grant', function (): void {
    // The Slice 0 trap: without firstOrCreate, the conditional UPDATE matches zero
    // rows on a couple that never earned anything, so the flag is never stamped and
    // the grant never happens — while the endpoint still answers {claimed:true}.
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    expect(CoupleReward::query()->where('couple_id', $coupleId)->exists())->toBeFalse();

    ClaimRatingRewardAction::run($coupleId);

    $reward = CoupleReward::query()->where('couple_id', $coupleId)->firstOrFail();
    expect($reward->credits)->toBe(5)
        ->and($reward->rating_reward_claimed_at)->not->toBeNull();
});
