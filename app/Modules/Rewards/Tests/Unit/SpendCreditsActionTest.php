<?php

use App\Modules\Rewards\Actions\GrantCreditsAction;
use App\Modules\Rewards\Actions\SpendCreditsAction;

test('spending debits the balance and reports success', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    GrantCreditsAction::run($coupleId, 3);

    expect(SpendCreditsAction::run($coupleId, 1))->toBeTrue()
        ->and(creditsOfCouple($coupleId))->toBe(2);
});

test('spending more than the balance changes nothing', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    GrantCreditsAction::run($coupleId, 1);

    expect(SpendCreditsAction::run($coupleId, 2))->toBeFalse()
        ->and(creditsOfCouple($coupleId))->toBe(1);
});

test('a couple that never earned anything cannot spend', function (): void {
    $user = createUserWithCouple();

    // No account row exists yet — reading it must not create one either.
    expect(SpendCreditsAction::run(activeCoupleOf($user)->id, 1))->toBeFalse();
});

test('the whole balance can be spent down to zero', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    GrantCreditsAction::run($coupleId, 2);

    expect(SpendCreditsAction::run($coupleId, 2))->toBeTrue()
        ->and(creditsOfCouple($coupleId))->toBe(0);
});
