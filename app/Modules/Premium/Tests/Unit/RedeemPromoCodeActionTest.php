<?php

use App\Modules\Premium\Actions\RedeemPromoCodeAction;
use App\Modules\Premium\Exceptions\InvalidPromoCodeException;
use App\Modules\Premium\Exceptions\PromoCodeAlreadyRedeemedException;
use App\Modules\Premium\Models\PromoCode;
use App\Modules\Premium\Support\PromoCodeKind;
use Illuminate\Support\Facades\DB;

test('a valid code is redeemed, registered and counted', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    $promoCode = PromoCode::factory()->create(['code' => 'JAITY-2026']);

    expect(RedeemPromoCodeAction::run($couple->id, 'JAITY-2026'))->toBe(PromoCodeKind::FullDeck)
        ->and(DB::table('couple_redeemed_codes')->where('couple_id', $couple->id)->count())->toBe(1)
        ->and($promoCode->fresh()->used_count)->toBe(1);
});

test('the code is case-insensitive and forgiving about stray spaces', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    PromoCode::factory()->create(['code' => 'JAITY-2026']);

    // People retype these off a card.
    expect(RedeemPromoCodeAction::run($couple->id, '  jaity-2026 '))->toBe(PromoCodeKind::FullDeck);
});

test('an unknown code is refused', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());

    expect(fn () => RedeemPromoCodeAction::run($couple->id, 'NIE-ISTNIEJE'))
        ->toThrow(InvalidPromoCodeException::class);
});

test('an expired code is refused and leaves no register entry', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    PromoCode::factory()->expired()->create(['code' => 'STARY']);

    expect(fn () => RedeemPromoCodeAction::run($couple->id, 'STARY'))
        ->toThrow(InvalidPromoCodeException::class)
        ->and(DB::table('couple_redeemed_codes')->count())->toBe(0);
});

test('a code whose uses are spent is refused', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    PromoCode::factory()->exhausted()->create(['code' => 'ZUZYTY']);

    expect(fn () => RedeemPromoCodeAction::run($couple->id, 'ZUZYTY'))
        ->toThrow(InvalidPromoCodeException::class);
});

test('the same couple cannot redeem the same code twice', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    $promoCode = PromoCode::factory()->create(['code' => 'JAITY-2026']);

    RedeemPromoCodeAction::run($couple->id, 'JAITY-2026');

    expect(fn () => RedeemPromoCodeAction::run($couple->id, 'JAITY-2026'))
        ->toThrow(PromoCodeAlreadyRedeemedException::class)
        // The second attempt must not burn another use of a limited code.
        ->and($promoCode->fresh()->used_count)->toBe(1);
});

test('two couples may redeem the same unlimited code', function (): void {
    $first = activeCoupleOf(createUserWithCouple());
    $second = activeCoupleOf(createUserWithCouple());
    $promoCode = PromoCode::factory()->create(['code' => 'WSPOLNY']);

    RedeemPromoCodeAction::run($first->id, 'WSPOLNY');
    RedeemPromoCodeAction::run($second->id, 'WSPOLNY');

    expect($promoCode->fresh()->used_count)->toBe(2)
        ->and(DB::table('couple_redeemed_codes')->count())->toBe(2);
});

test('a limited code stops at its budget', function (): void {
    $first = activeCoupleOf(createUserWithCouple());
    $second = activeCoupleOf(createUserWithCouple());
    PromoCode::factory()->create(['code' => 'JEDEN', 'max_uses' => 1]);

    RedeemPromoCodeAction::run($first->id, 'JEDEN');

    expect(fn () => RedeemPromoCodeAction::run($second->id, 'JEDEN'))
        ->toThrow(InvalidPromoCodeException::class);
});
