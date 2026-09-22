<?php

use App\Modules\Game\Models\Couple;
use App\Modules\Memories\Models\Memory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Models\User;
use Youandme\Auth\Support\AppleAuthClientInterface;
use Youandme\Auth\Support\AppleAuthException;
use Youandme\Auth\Support\AppleTokenGrant;
use Youandme\Auth\Support\AppleTokenVerifierInterface;
use Youandme\Auth\Support\GoogleTokenVerifierInterface;

function mockSocialSignIn(string $verifier, string $sub, string $email): void
{
    test()->mock($verifier)
        ->shouldReceive('verify')
        ->andReturn(['sub' => $sub, 'email' => $email, 'email_verified' => true, 'name' => null]);
}

test('an email account is deleted with its couple and everything they left', function (): void {
    $user = createUserWithCouple(['email' => 'ola@example.com']);
    seedAccountFootprint($user);
    $coupleId = $user->active_couple_id;
    $control = createUserWithCouple();
    seedAccountFootprint($control);
    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/me')->assertNoContent();

    expect(User::withTrashed()->whereKey($user->id)->exists())->toBeFalse()
        ->and(Couple::query()->whereKey($coupleId)->exists())->toBeFalse()
        ->and(Memory::withTrashed()->where('couple_id', $coupleId)->count())->toBe(0)
        ->and($control->fresh())->not->toBeNull()
        ->and(Memory::withTrashed()->where('couple_id', $control->active_couple_id)->count())->toBe(2);
});

test('after deletion the user token no longer works', function (): void {
    $user = createUserWithCouple();
    $token = $user->createToken('mobile')->plainTextToken;
    $user->createToken('tablet');

    $this->withToken($token)->deleteJson('/api/v1/me')->assertNoContent();
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    expect(DB::table('personal_access_tokens')->count())->toBe(0);
});

test('deleting an account leaves an audit line with the ulid and no email', function (): void {
    $user = createUserWithCouple(['email' => 'ola@example.com']);
    Sanctum::actingAs($user);
    Log::spy();

    $this->deleteJson('/api/v1/me')->assertNoContent();

    Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context) use ($user): bool {
        return $message === 'Account deleted'
            && $context['user_ulid'] === $user->ulid
            && isset($context['deleted_at'])
            && ! str_contains((string) json_encode($context), 'ola@example.com');
    });
});

test('an account in a shared couple is refused with 409 and nothing is deleted', function (): void {
    $user = createUserWithCouple();
    $partner = createUserWithCouple();
    // Nothing in the app sets user_b_id yet; the remote game will.
    Couple::factory()->create(['user_a_id' => $partner->id, 'user_b_id' => $user->id]);
    seedAccountFootprint($user);
    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/me')
        ->assertConflict()
        ->assertJsonPath('message', 'Tego konta nie da się jeszcze usunąć samodzielnie — napisz do nas na kontakt@jaity.app.');

    expect($user->fresh()?->active_couple_id)->not->toBeNull()
        ->and(Memory::withTrashed()->where('couple_id', $user->active_couple_id)->count())->toBe(2);
});

test('deleting an account requires authentication', function (): void {
    $this->deleteJson('/api/v1/me')->assertUnauthorized();
});

test('deleting an account is throttled', function (): void {
    // The limit is keyed by the authenticated user, and a real deletion would
    // leave nobody to throttle — so use an account that is refused and stays.
    $user = createUserWithCouple();
    Couple::factory()->create(['user_a_id' => createUserWithCouple()->id, 'user_b_id' => $user->id]);
    Sanctum::actingAs($user);

    for ($i = 0; $i < 5; $i++) {
        $this->deleteJson('/api/v1/me')->assertConflict();
    }

    $this->deleteJson('/api/v1/me')->assertTooManyRequests();
});

test('the same email can register again after the account is deleted', function (): void {
    $user = createUserWithCouple(['email' => 'ola@example.com', 'nickname' => 'ola_test']);
    Sanctum::actingAs($user);
    $this->deleteJson('/api/v1/me')->assertNoContent();

    $this->postJson('/api/v1/auth/register', [
        'email' => 'ola@example.com',
        'password' => 'tajne-haslo-123',
        'nickname' => 'ola_test',
    ])->assertCreated();
});

test('the same Apple ID can sign in again after the account is deleted', function (): void {
    $user = createUserWithCouple(['email' => 'relay@privaterelay.appleid.com', 'apple_id' => 'apple-abc']);
    Sanctum::actingAs($user);
    $this->deleteJson('/api/v1/me')->assertNoContent();

    mockSocialSignIn(AppleTokenVerifierInterface::class, 'apple-abc', 'relay@privaterelay.appleid.com');

    $this->postJson('/api/v1/auth/apple', ['id_token' => 'fake-token'])
        ->assertCreated()
        ->assertJsonPath('user.is_apple_linked', true);
    expect(User::query()->where('apple_id', 'apple-abc')->sole()->id)->not->toBe($user->id);
});

test('the same Google account can sign in again after the account is deleted', function (): void {
    $user = createUserWithCouple(['email' => 'ola@gmail.com', 'google_id' => 'google-abc']);
    Sanctum::actingAs($user);
    $this->deleteJson('/api/v1/me')->assertNoContent();

    mockSocialSignIn(GoogleTokenVerifierInterface::class, 'google-abc', 'ola@gmail.com');

    $this->postJson('/api/v1/auth/google', ['id_token' => 'fake-token'])->assertCreated();
});

/** An Apple-linked account, signed in, with Apple's side mocked. */
function appleAccount(): array
{
    $user = createUserWithCouple(['apple_id' => 'apple-sub-1']);
    Sanctum::actingAs($user);
    $client = test()->mock(AppleAuthClientInterface::class);
    $client->shouldReceive('isConfigured')->andReturnTrue()->byDefault();

    return [$user, $client];
}

test('an Apple account with a fresh code has its Apple tokens revoked and is deleted', function (): void {
    [$user, $client] = appleAccount();
    $client->shouldReceive('exchangeAuthorizationCode')->with('fresh-code')->once()->andReturn(new AppleTokenGrant('r-token', 'id-token'));
    mockSocialSignIn(AppleTokenVerifierInterface::class, 'apple-sub-1', 'relay@privaterelay.appleid.com');
    $client->shouldReceive('revokeRefreshToken')->with('r-token')->once();

    $this->deleteJson('/api/v1/me', ['apple_authorization_code' => 'fresh-code'])->assertNoContent();

    expect(User::withTrashed()->whereKey($user->id)->exists())->toBeFalse();
});

test('an Apple account without a code is still deleted', function (): void {
    [$user, $client] = appleAccount();
    $client->shouldNotReceive('exchangeAuthorizationCode');
    Log::spy();

    $this->deleteJson('/api/v1/me')->assertNoContent();

    expect(User::withTrashed()->whereKey($user->id)->exists())->toBeFalse();
    Log::shouldHaveReceived('warning')->with('Apple token revocation skipped: no authorization code sent')->once();
});

test('an Apple account is still deleted when Apple fails', function (): void {
    [$user, $client] = appleAccount();
    $client->shouldReceive('exchangeAuthorizationCode')->andThrow(new AppleAuthException('Apple /auth/token failed with HTTP 400 (invalid_grant).'));

    $this->deleteJson('/api/v1/me', ['apple_authorization_code' => 'stale-code'])->assertNoContent();

    expect(User::withTrashed()->whereKey($user->id)->exists())->toBeFalse();
});

test('an Apple account is still deleted when the Apple key is not configured', function (): void {
    [$user, $client] = appleAccount();
    $client->shouldReceive('isConfigured')->andReturnFalse();
    $client->shouldNotReceive('exchangeAuthorizationCode');

    $this->deleteJson('/api/v1/me', ['apple_authorization_code' => 'fresh-code'])->assertNoContent();

    expect(User::withTrashed()->whereKey($user->id)->exists())->toBeFalse();
});

test('a code from a different Apple ID revokes nothing, logs no identifier and still deletes', function (): void {
    [$user, $client] = appleAccount();
    $client->shouldReceive('exchangeAuthorizationCode')->andReturn(new AppleTokenGrant('r-token', 'id-token'));
    mockSocialSignIn(AppleTokenVerifierInterface::class, 'apple-sub-OTHER', 'other@privaterelay.appleid.com');
    $client->shouldNotReceive('revokeRefreshToken');
    Log::spy();

    $this->deleteJson('/api/v1/me', ['apple_authorization_code' => 'fresh-code'])->assertNoContent();

    expect(User::withTrashed()->whereKey($user->id)->exists())->toBeFalse();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context = []): bool => $message === 'Apple token revocation skipped: authorization code belongs to a different Apple ID'
        && $context === [])->once();
});

test('a refused deletion does not touch Apple', function (): void {
    [$user, $client] = appleAccount();
    Couple::factory()->create(['user_a_id' => createUserWithCouple()->id, 'user_b_id' => $user->id]);
    $client->shouldNotReceive('exchangeAuthorizationCode');

    $this->deleteJson('/api/v1/me', ['apple_authorization_code' => 'fresh-code'])->assertConflict();
});

test('the Apple authorization code must be a string', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    $this->deleteJson('/api/v1/me', ['apple_authorization_code' => ['nope']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('apple_authorization_code');
});
