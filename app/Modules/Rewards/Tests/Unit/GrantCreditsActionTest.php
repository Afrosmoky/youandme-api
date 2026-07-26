<?php

use App\Modules\Rewards\Actions\GrantCreditsAction;
use App\Modules\Rewards\Models\CoupleReward;

test('GrantCreditsAction creates the reward account on the first grant and increments credits', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    expect(CoupleReward::query()->where('couple_id', $coupleId)->exists())->toBeFalse();

    GrantCreditsAction::run($coupleId, 5);

    expect(creditsOfCouple($coupleId))->toBe(5)
        ->and(CoupleReward::query()->where('couple_id', $coupleId)->count())->toBe(1);
});

test('GrantCreditsAction accumulates across calls without duplicating the account', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    GrantCreditsAction::run($coupleId, 5);
    GrantCreditsAction::run($coupleId, 5);

    expect(creditsOfCouple($coupleId))->toBe(10)
        ->and(CoupleReward::query()->where('couple_id', $coupleId)->count())->toBe(1);
});
