<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Actions\UnlockQuestionForCoupleAction;
use App\Modules\Game\Support\UnlockSource;
use App\Modules\Premium\Models\PromoCode;
use App\Modules\Rewards\Actions\GrantCreditsAction;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Models\User;

/*
 | POST /redeem — the app layer turning a Premium code into a Game entitlement in
 | one transaction. The second grantor of the same unlock; credits were the first.
 */

test('a valid code opens the whole closed deck', function (): void {
    Question::factory()->count(2)->create();
    $locked = Question::factory()->locked()->count(3)->create();
    PromoCode::factory()->create(['code' => 'JAITY-2026']);

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/redeem', ['code' => 'JAITY-2026'])
        ->assertOk()
        ->assertJsonPath('unlocked', 3)
        ->assertJsonPath('deck.locked_total', 3)
        ->assertJsonPath('deck.unlocked_count', 3)
        ->assertJsonPath('deck.complete', true);

    expect(DB::table('couple_unlocked_questions')->where('couple_id', $coupleId)->count())->toBe(3)
        ->and(DB::table('couple_unlocked_questions')->where('source', 'premium')->count())->toBe(3)
        ->and($locked->pluck('id')->all())->toHaveCount(3);
});

test('redeeming costs no credits', function (): void {
    Question::factory()->locked()->create();
    PromoCode::factory()->create(['code' => 'JAITY-2026']);

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    GrantCreditsAction::run($coupleId, 4);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/redeem', ['code' => 'JAITY-2026'])->assertOk();

    // A code is a different grantor, not a credit purchase — the balance is
    // untouched and stays spendable on whatever comes next.
    expect(creditsOfCouple($coupleId))->toBe(4);
});

test('cards already bought with credits keep their source and are not doubled', function (): void {
    $alreadyOwned = Question::factory()->locked()->create();
    Question::factory()->locked()->count(2)->create();
    PromoCode::factory()->create(['code' => 'JAITY-2026']);

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    UnlockQuestionForCoupleAction::run($coupleId, $alreadyOwned->id, UnlockSource::Credits);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/redeem', ['code' => 'JAITY-2026'])
        ->assertOk()
        ->assertJsonPath('unlocked', 2)
        ->assertJsonPath('deck.unlocked_count', 3);

    expect(DB::table('couple_unlocked_questions')->where('couple_id', $coupleId)->count())->toBe(3)
        ->and(DB::table('couple_unlocked_questions')->where('question_id', $alreadyOwned->id)->value('source'))
        ->toBe('credits');
});

test('an unknown code unlocks nothing', function (): void {
    Question::factory()->locked()->create();
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/redeem', ['code' => 'NIE-ISTNIEJE'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Nie znamy takiego kodu.');

    expect(DB::table('couple_unlocked_questions')->count())->toBe(0);
});

test('an expired code unlocks nothing', function (): void {
    Question::factory()->locked()->create();
    PromoCode::factory()->expired()->create(['code' => 'STARY']);
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/redeem', ['code' => 'STARY'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Ten kod już wygasł.');

    expect(DB::table('couple_unlocked_questions')->count())->toBe(0);
});

test('a code exhausted by other couples rolls the whole redemption back', function (): void {
    Question::factory()->locked()->create();
    PromoCode::factory()->exhausted()->create(['code' => 'ZUZYTY']);
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/redeem', ['code' => 'ZUZYTY'])->assertUnprocessable();

    // Neither the register entry nor the deck may survive a failed redemption.
    expect(DB::table('couple_redeemed_codes')->count())->toBe(0)
        ->and(DB::table('couple_unlocked_questions')->count())->toBe(0);
});

test('redeeming the same code twice is a conflict', function (): void {
    Question::factory()->locked()->create();
    $promoCode = PromoCode::factory()->create(['code' => 'JAITY-2026']);
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/redeem', ['code' => 'JAITY-2026'])->assertOk();
    $this->postJson('/api/v1/redeem', ['code' => 'JAITY-2026'])->assertConflict();

    expect($promoCode->fresh()->used_count)->toBe(1)
        ->and(DB::table('couple_redeemed_codes')->count())->toBe(1);
});

test('a redeemed deck actually becomes playable', function (): void {
    Question::factory()->locked()->count(2)->create();
    PromoCode::factory()->create(['code' => 'JAITY-2026']);
    Sanctum::actingAs(createUserWithCouple());

    // Before: nothing free to draw, so no session can start.
    $this->postJson('/api/v1/sessions/start')->assertUnprocessable();

    $this->postJson('/api/v1/redeem', ['code' => 'JAITY-2026'])->assertOk();

    $this->postJson('/api/v1/sessions/start')
        ->assertCreated()
        ->assertJsonPath('session.remaining_count', 2);
});

test('the redemption lands on the couple from the token', function (): void {
    Question::factory()->locked()->create();
    PromoCode::factory()->create(['code' => 'JAITY-2026']);

    $redeemer = createUserWithCouple();
    $stranger = createUserWithCouple();
    Sanctum::actingAs($redeemer);

    $this->postJson('/api/v1/redeem', ['code' => 'JAITY-2026'])->assertOk();

    expect(DB::table('couple_unlocked_questions')->where('couple_id', activeCoupleOf($stranger)->id)->count())
        ->toBe(0)
        ->and(DB::table('couple_redeemed_codes')->where('couple_id', activeCoupleOf($stranger)->id)->count())
        ->toBe(0);
});

test('the code is required', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/redeem', [])->assertUnprocessable();
});

test('redeeming requires a token', function (): void {
    $this->postJson('/api/v1/redeem', ['code' => 'JAITY-2026'])->assertUnauthorized();
});

test('a user without a couple cannot redeem', function (): void {
    PromoCode::factory()->create(['code' => 'JAITY-2026']);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/redeem', ['code' => 'JAITY-2026'])->assertNotFound();
});
