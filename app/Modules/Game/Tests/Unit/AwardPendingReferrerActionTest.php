<?php

use App\Modules\Game\Actions\AwardPendingReferrerAction;
use App\Modules\Game\Actions\RecordReferralAction;
use App\Modules\Game\Models\Referral;

test('AwardPendingReferrerAction pays the referrer +5 and stamps referrer_awarded_at', function (): void {
    $referrer = createUserWithCouple();
    $referred = createUserWithCouple();
    RecordReferralAction::run($referrer->id, $referred->id);

    AwardPendingReferrerAction::run($referred->id);

    expect(creditsOfCouple(activeCoupleOf($referrer)->id))->toBe(5);
    expect(Referral::where('referred_user_id', $referred->id)->firstOrFail()->referrer_awarded_at)->not->toBeNull();
});

test('AwardPendingReferrerAction is idempotent — a second call does not pay again', function (): void {
    $referrer = createUserWithCouple();
    $referred = createUserWithCouple();
    RecordReferralAction::run($referrer->id, $referred->id);

    AwardPendingReferrerAction::run($referred->id);
    AwardPendingReferrerAction::run($referred->id);

    expect(creditsOfCouple(activeCoupleOf($referrer)->id))->toBe(5);
});

test('AwardPendingReferrerAction is a no-op when the user has no referral', function (): void {
    $user = createUserWithCouple();

    AwardPendingReferrerAction::run($user->id);

    expect(creditsOfCouple(activeCoupleOf($user)->id))->toBe(0);
});
