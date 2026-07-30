<?php

use App\Modules\Rewards\Actions\IssueAdRewardNonceAction;
use App\Modules\Rewards\Models\AdRewardNonce;
use Carbon\CarbonImmutable;

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

test('rewards:prune-ad-nonces drops old nonces and keeps recent ones', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-01 10:00:00', 'UTC'));
    $old = IssueAdRewardNonceAction::run($coupleId, $user->id);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-30 10:00:00', 'UTC'));
    $fresh = IssueAdRewardNonceAction::run($coupleId, $user->id);

    $this->artisan('rewards:prune-ad-nonces')->assertSuccessful();

    expect(AdRewardNonce::query()->pluck('nonce')->all())->toBe([$fresh])
        ->and(AdRewardNonce::query()->where('nonce', $old)->exists())->toBeFalse();
});

test('an unspent nonce is pruned like any other', function (): void {
    $user = createUserWithCouple();

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-01 10:00:00', 'UTC'));
    IssueAdRewardNonceAction::run(activeCoupleOf($user)->id, $user->id);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-30 10:00:00', 'UTC'));
    $this->artisan('rewards:prune-ad-nonces')->assertSuccessful();

    // A token whose callback never arrived is dead weight — and after a week its
    // ad view certainly did not happen.
    expect(AdRewardNonce::query()->count())->toBe(0);
});
