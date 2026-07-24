<?php

use App\Modules\Game\Models\Referral;
use Youandme\Auth\Models\User;

/**
 * @return array<string, string>
 */
function registerPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'nowy@example.com',
        'password' => 'tajne-haslo-123',
        'nickname' => 'nowy_user',
    ], $overrides);
}

test('registration with a valid referrer nickname records the referral and grants the registrant +5', function (): void {
    $referrer = createUserWithCouple(['nickname' => 'polecajacy']);

    $this->postJson('/api/v1/auth/register', registerPayload(['referrer_nickname' => 'polecajacy']))
        ->assertCreated();

    $referred = User::where('email', 'nowy@example.com')->firstOrFail();

    $referral = Referral::where('referred_user_id', $referred->id)->firstOrFail();
    expect($referral->referrer_user_id)->toBe($referrer->id)
        ->and($referral->referrer_awarded_at)->toBeNull();

    // Registrant's half of the symmetric bonus is immediate.
    expect(activeCoupleOf($referred)->card_balance)->toBe(5);
    // The referrer is not paid yet — that waits for the referred user's first open.
    expect(activeCoupleOf($referrer)->card_balance)->toBe(0);
});

test('registration without a referrer nickname creates no referral and leaves card_balance 0', function (): void {
    $this->postJson('/api/v1/auth/register', registerPayload())
        ->assertCreated();

    $user = User::where('email', 'nowy@example.com')->firstOrFail();

    expect(Referral::count())->toBe(0)
        ->and(activeCoupleOf($user)->card_balance)->toBe(0);
});

test('registration with a non-existent referrer nickname is rejected 422 and creates nothing', function (): void {
    $this->postJson('/api/v1/auth/register', registerPayload(['referrer_nickname' => 'nieistnieje']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('referrer_nickname');

    // Transaction: neither the user nor any referral was created.
    expect(User::where('email', 'nowy@example.com')->exists())->toBeFalse()
        ->and(Referral::count())->toBe(0);
});

test('registration referring your own nickname is rejected 422 (self-referral)', function (): void {
    $this->postJson('/api/v1/auth/register', registerPayload(['nickname' => 'ja_sam', 'referrer_nickname' => 'ja_sam']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('referrer_nickname');

    expect(User::where('email', 'nowy@example.com')->exists())->toBeFalse();
});

test('an empty referrer nickname is treated as absent (no referral, still 201)', function (): void {
    $this->postJson('/api/v1/auth/register', registerPayload(['referrer_nickname' => '']))
        ->assertCreated();

    expect(Referral::count())->toBe(0);
});
