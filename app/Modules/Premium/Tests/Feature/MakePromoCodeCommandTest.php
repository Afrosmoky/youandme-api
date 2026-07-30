<?php

use App\Modules\Premium\Models\PromoCode;
use App\Modules\Premium\Support\PromoCodeKind;
use Carbon\CarbonImmutable;

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

test('premium:make-code creates an unlimited full-deck code', function (): void {
    $this->artisan('premium:make-code', ['code' => 'JAITY-2026'])->assertSuccessful();

    $promoCode = PromoCode::query()->where('code', 'JAITY-2026')->firstOrFail();

    expect($promoCode->kind)->toBe(PromoCodeKind::FullDeck)
        ->and($promoCode->max_uses)->toBeNull()
        ->and($promoCode->expires_at)->toBeNull()
        ->and($promoCode->used_count)->toBe(0)
        ->and($promoCode->ulid)->not->toBeNull();
});

test('a code is generated when none is given', function (): void {
    $this->artisan('premium:make-code')->assertSuccessful();

    expect(PromoCode::query()->value('code'))->toStartWith('JAITY-');
});

test('limits and expiry are applied, with expiry lasting the whole day', function (): void {
    $this->artisan('premium:make-code', [
        'code' => 'LIMIT',
        '--max-uses' => '50',
        '--expires-at' => '2026-12-31',
    ])->assertSuccessful();

    $promoCode = PromoCode::query()->where('code', 'LIMIT')->firstOrFail();

    expect($promoCode->max_uses)->toBe(50)
        ->and($promoCode->expires_at->toDateTimeString())->toBe('2026-12-31 23:59:59');
});

test('a duplicate code is refused instead of overwriting', function (): void {
    PromoCode::factory()->create(['code' => 'JAITY-2026']);

    $this->artisan('premium:make-code', ['code' => 'jaity-2026'])->assertFailed();

    expect(PromoCode::query()->count())->toBe(1);
});
