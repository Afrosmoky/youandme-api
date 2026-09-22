<?php

use App\Modules\Game\Models\Couple;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Youandme\Auth\Events\UserRegistered;
use Youandme\Auth\Models\User;
use Youandme\Auth\Support\GoogleTokenVerifierInterface;
use Youandme\Auth\Support\SocialTokenException;

test('google sign-in creates a new user and returns 201', function (): void {
    Event::fake([UserRegistered::class]);

    $this->mock(GoogleTokenVerifierInterface::class)
        ->shouldReceive('verify')
        ->once()
        ->andReturn([
            'sub' => 'google-123',
            'email' => 'nowy@example.com',
            'email_verified' => true,
            'name' => 'Nowy User',
        ]);

    $response = $this->postJson('/api/v1/auth/google', ['id_token' => 'fake-token']);

    $response->assertCreated()
        ->assertJsonStructure([
            'user' => ['ulid', 'email', 'nickname', 'email_verified_at', 'created_at'],
            'token',
        ])
        ->assertJsonPath('user.email', 'nowy@example.com')
        ->assertJsonPath('user.is_google_linked', true)
        ->assertJsonPath('user.is_apple_linked', false);

    $response->assertJsonStructure(['couple' => ['ulid', 'partner_name_local']])
        ->assertJsonPath('couple.partner_name_local', null);

    $user = User::where('email', 'nowy@example.com')->firstOrFail();
    expect($user->google_id)->toBe('google-123');
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->active_couple_id)->not->toBeNull();
    Event::assertDispatched(UserRegistered::class);
});

test('google sign-in logs in an existing user and returns 200', function (): void {
    Event::fake([UserRegistered::class]);
    $user = createUserWithCouple(['email' => 'stary@example.com']);

    $this->mock(GoogleTokenVerifierInterface::class)
        ->shouldReceive('verify')
        ->andReturn([
            'sub' => 'google-999',
            'email' => 'stary@example.com',
            'email_verified' => true,
            'name' => 'Stary',
        ]);

    $response = $this->postJson('/api/v1/auth/google', ['id_token' => 'fake-token']);

    $response->assertOk()
        ->assertJsonPath('user.email', 'stary@example.com')
        ->assertJsonPath('couple.ulid', activeCoupleOf($user)->ulid);
    expect($user->fresh()->google_id)->toBe('google-999');
    expect(User::count())->toBe(1);
    expect(Couple::count())->toBe(1);
    Event::assertNotDispatched(UserRegistered::class);
});

test('google sign-in with an invalid token returns 401', function (): void {
    $this->mock(GoogleTokenVerifierInterface::class)
        ->shouldReceive('verify')
        ->andThrow(new SocialTokenException('Invalid ID token'));

    $this->postJson('/api/v1/auth/google', ['id_token' => 'bad-token'])
        ->assertUnauthorized();
});

test('google sign-in requires an id_token', function (): void {
    $this->postJson('/api/v1/auth/google', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_token');
});

test('an address the provider has not verified does not sign into an existing account', function (): void {
    // The takeover: an account at the provider opened on someone else's address and
    // left unverified. The token is genuine and issued for us, so nothing but this
    // rule stands between it and the victim's account.
    $victim = createUserWithCouple(['email' => 'ofiara@example.com']);

    $this->mock(GoogleTokenVerifierInterface::class)
        ->shouldReceive('verify')
        ->andReturn([
            'sub' => 'google-attacker',
            'email' => 'ofiara@example.com',
            'email_verified' => false,
            'name' => 'Nie Ja',
        ]);

    $this->postJson('/api/v1/auth/google', ['id_token' => 'fake-token'])
        ->assertStatus(422);

    // Nothing was attached, nothing was created, and — the part that matters — no
    // token was issued: finding the account would have been signing into it.
    expect($victim->fresh()->google_id)->toBeNull()
        ->and(User::count())->toBe(1)
        ->and(DB::table('personal_access_tokens')->count())->toBe(0);
});

test('an address the provider has not verified does not open an account either', function (): void {
    // Refused even though the address is free. Letting it through would seat someone
    // on an address they have not proven, and wall out its owner: arriving later with
    // a verified account, they would carry a different sub against the same address
    // and meet the conflict rule instead of their own account.
    Event::fake([UserRegistered::class]);

    $this->mock(GoogleTokenVerifierInterface::class)
        ->shouldReceive('verify')
        ->andReturn([
            'sub' => 'google-squatter',
            'email' => 'niczyj@example.com',
            'email_verified' => false,
            'name' => null,
        ]);

    $this->postJson('/api/v1/auth/google', ['id_token' => 'fake-token'])
        ->assertStatus(422);

    expect(User::count())->toBe(0);
    Event::assertNotDispatched(UserRegistered::class);
});

test('a second google account claiming a linked address is refused, not signed in', function (): void {
    // Same address, different sub. A provider's sub is stable for the life of the
    // account, so this is always a foreign account claiming the address — never the
    // same person coming back.
    $user = createUserWithCouple(['email' => 'zajete@example.com', 'google_id' => 'google-first']);

    $this->mock(GoogleTokenVerifierInterface::class)
        ->shouldReceive('verify')
        ->andReturn([
            'sub' => 'google-second',
            'email' => 'zajete@example.com',
            'email_verified' => true,
            'name' => null,
        ]);

    $this->postJson('/api/v1/auth/google', ['id_token' => 'fake-token'])
        ->assertStatus(409);

    // The old guard left the column alone but signed the stranger in anyway.
    expect($user->fresh()->google_id)->toBe('google-first')
        ->and(DB::table('personal_access_tokens')->count())->toBe(0);
});

test('a rejected token does not echo the verifier back to the caller', function (): void {
    // The verifier speaks about our internals — expiry, signature, configuration.
    // Whatever it says must not reach whoever is holding the token.
    $this->mock(GoogleTokenVerifierInterface::class)
        ->shouldReceive('verify')
        ->andThrow(new SocialTokenException('Signature verification failed for kid=abc123'));

    $response = $this->postJson('/api/v1/auth/google', ['id_token' => 'bad-token'])
        ->assertUnauthorized();

    expect($response->getContent())->not->toContain('Signature verification failed')
        ->and($response->getContent())->not->toContain('kid=abc123');
});
