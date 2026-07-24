<?php

use App\Modules\Game\Actions\ClaimShareRewardAction;

test('ClaimShareRewardAction grants +5 and stamps the flag on the first claim', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    expect($couple->card_balance)->toBe(0);

    ClaimShareRewardAction::run($couple);

    $couple->refresh();
    expect($couple->card_balance)->toBe(5)
        ->and($couple->share_reward_claimed_at)->not->toBeNull();
});

test('ClaimShareRewardAction is a no-op on the second claim (no double grant)', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);

    ClaimShareRewardAction::run($couple);
    $stamp = $couple->fresh()->share_reward_claimed_at;

    ClaimShareRewardAction::run($couple);

    $couple->refresh();
    expect($couple->card_balance)->toBe(5)
        ->and($couple->share_reward_claimed_at->equalTo($stamp))->toBeTrue();
});
