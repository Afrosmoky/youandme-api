<?php

use App\Modules\Rewards\Actions\ClaimShareRewardAction;
use App\Modules\Rewards\Models\CoupleReward;

test('ClaimShareRewardAction grants +5 and stamps the flag on the first claim', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    expect(creditsOfCouple($coupleId))->toBe(0);

    ClaimShareRewardAction::run($coupleId);

    $reward = CoupleReward::query()->where('couple_id', $coupleId)->firstOrFail();
    expect($reward->credits)->toBe(5)
        ->and($reward->share_reward_claimed_at)->not->toBeNull();
});

test('ClaimShareRewardAction is a no-op on the second claim (no double grant)', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    ClaimShareRewardAction::run($coupleId);
    $stamp = CoupleReward::query()->where('couple_id', $coupleId)->firstOrFail()->share_reward_claimed_at;

    ClaimShareRewardAction::run($coupleId);

    $reward = CoupleReward::query()->where('couple_id', $coupleId)->firstOrFail();
    expect($reward->credits)->toBe(5)
        ->and($reward->share_reward_claimed_at->equalTo($stamp))->toBeTrue();
});
