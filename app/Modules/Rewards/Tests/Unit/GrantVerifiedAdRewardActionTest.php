<?php

use App\Modules\Rewards\Actions\GrantVerifiedAdRewardAction;
use App\Modules\Rewards\Actions\IssueAdRewardNonceAction;
use App\Modules\Rewards\Models\AdRewardNonce;
use App\Modules\Rewards\Models\CoupleDailyAdReward;
use App\Modules\Rewards\Support\AdRewardOutcome;
use Carbon\CarbonImmutable;

/*
 | The grant itself, with the signature check already done. Cap and credits mirror
 | AdRewardPolicy (5 ads x 1 credit).
 */

$today = fn (): CarbonImmutable => CarbonImmutable::parse('2026-07-30', 'UTC')->startOfDay();

test('a fresh nonce grants a credit and opens the daily bucket', function () use ($today): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    $nonce = IssueAdRewardNonceAction::run($coupleId, $user->id);

    expect(GrantVerifiedAdRewardAction::run($nonce, $today(), deckComplete: false))
        ->toBe(AdRewardOutcome::Granted)
        ->and(creditsOfCouple($coupleId))->toBe(1)
        ->and(CoupleDailyAdReward::query()->where('couple_id', $coupleId)->value('count'))->toBe(1);
});

test('replaying the same nonce grants nothing a second time', function () use ($today): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    $nonce = IssueAdRewardNonceAction::run($coupleId, $user->id);

    GrantVerifiedAdRewardAction::run($nonce, $today(), deckComplete: false);

    expect(GrantVerifiedAdRewardAction::run($nonce, $today(), deckComplete: false))
        ->toBe(AdRewardOutcome::NonceAlreadyUsed)
        ->and(creditsOfCouple($coupleId))->toBe(1);
});

test('an unknown nonce grants nothing', function () use ($today): void {
    $user = createUserWithCouple();

    expect(GrantVerifiedAdRewardAction::run('never-issued', $today(), deckComplete: false))
        ->toBe(AdRewardOutcome::NonceUnknown)
        ->and(creditsOfCouple(activeCoupleOf($user)->id))->toBe(0);
});

test('the credit lands on the nonce owner, whoever the caller thinks it is', function () use ($today): void {
    $owner = createUserWithCouple();
    $stranger = createUserWithCouple();
    $ownerCoupleId = activeCoupleOf($owner)->id;
    $strangerCoupleId = activeCoupleOf($stranger)->id;

    GrantVerifiedAdRewardAction::run(
        IssueAdRewardNonceAction::run($ownerCoupleId, $owner->id),
        $today(),
        deckComplete: false,
    );

    expect(creditsOfCouple($ownerCoupleId))->toBe(1)
        ->and(creditsOfCouple($strangerCoupleId))->toBe(0);
});

test('the sixth ad of the day is capped', function () use ($today): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    foreach (range(1, 5) as $ignored) {
        GrantVerifiedAdRewardAction::run(
            IssueAdRewardNonceAction::run($coupleId, $user->id),
            $today(),
            deckComplete: false,
        );
    }

    $sixth = IssueAdRewardNonceAction::run($coupleId, $user->id);

    expect(GrantVerifiedAdRewardAction::run($sixth, $today(), deckComplete: false))
        ->toBe(AdRewardOutcome::CapReached)
        ->and(creditsOfCouple($coupleId))->toBe(5);

    // The nonce is spent either way — it cannot be saved for tomorrow.
    expect(AdRewardNonce::query()->where('nonce', $sixth)->value('consumed_at'))->not->toBeNull();
});

test('the cap is per local day, so the next day grants again', function () use ($today): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    foreach (range(1, 5) as $ignored) {
        GrantVerifiedAdRewardAction::run(
            IssueAdRewardNonceAction::run($coupleId, $user->id),
            $today(),
            deckComplete: false,
        );
    }

    $tomorrow = $today()->addDay();

    expect(GrantVerifiedAdRewardAction::run(IssueAdRewardNonceAction::run($coupleId, $user->id), $tomorrow, deckComplete: false))
        ->toBe(AdRewardOutcome::Granted)
        ->and(creditsOfCouple($coupleId))->toBe(6);
});

test('a complete deck burns the nonce without paying', function () use ($today): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    $nonce = IssueAdRewardNonceAction::run($coupleId, $user->id);

    expect(GrantVerifiedAdRewardAction::run($nonce, $today(), deckComplete: true))
        ->toBe(AdRewardOutcome::DeckComplete)
        ->and(creditsOfCouple($coupleId))->toBe(0)
        // No bucket either: the view never counted against the daily budget.
        ->and(CoupleDailyAdReward::query()->count())->toBe(0)
        ->and(AdRewardNonce::query()->where('nonce', $nonce)->value('consumed_at'))->not->toBeNull();

    // And it cannot be cashed in later, once new content makes the deck buyable.
    expect(GrantVerifiedAdRewardAction::run($nonce, $today(), deckComplete: false))
        ->toBe(AdRewardOutcome::NonceAlreadyUsed)
        ->and(creditsOfCouple($coupleId))->toBe(0);
});
