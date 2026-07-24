<?php

use App\Modules\Game\Actions\GrantCardsAction;

test('GrantCardsAction increments card_balance and returns the couple', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    expect($couple->card_balance)->toBe(0);

    $result = GrantCardsAction::run($couple, 5);

    expect($result->card_balance)->toBe(5)
        ->and($couple->fresh()->card_balance)->toBe(5);
});

test('GrantCardsAction accumulates across calls', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);

    GrantCardsAction::run($couple, 5);
    GrantCardsAction::run($couple, 5);

    expect($couple->fresh()->card_balance)->toBe(10);
});
