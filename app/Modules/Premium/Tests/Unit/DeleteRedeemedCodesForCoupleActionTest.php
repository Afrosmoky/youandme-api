<?php

use App\Modules\Premium\Actions\DeleteRedeemedCodesForCoupleAction;
use App\Modules\Premium\Actions\RedeemPromoCodeAction;
use App\Modules\Premium\Exceptions\InvalidPromoCodeException;
use App\Modules\Premium\Models\PromoCode;
use Illuminate\Support\Facades\DB;

test('it forgets the couple redemptions and leaves other couples alone', function (): void {
    $user = createUserWithCouple();
    seedAccountFootprint($user);
    $other = createUserWithCouple();
    seedAccountFootprint($other);

    DeleteRedeemedCodesForCoupleAction::run($user->active_couple_id);

    expect(DB::table('couple_redeemed_codes')->where('couple_id', $user->active_couple_id)->count())->toBe(0)
        ->and(DB::table('couple_redeemed_codes')->where('couple_id', $other->active_couple_id)->count())->toBe(1);
});

test('forgetting a redemption does not give a limited code its use back', function (): void {
    $user = createUserWithCouple();
    $code = PromoCode::factory()->create(['max_uses' => 1]);
    RedeemPromoCodeAction::run($user->active_couple_id, $code->code);

    DeleteRedeemedCodesForCoupleAction::run($user->active_couple_id);

    expect($code->fresh()->used_count)->toBe(1)
        ->and(fn () => RedeemPromoCodeAction::run(createUserWithCouple()->active_couple_id, $code->code))
        ->toThrow(InvalidPromoCodeException::class);
});
