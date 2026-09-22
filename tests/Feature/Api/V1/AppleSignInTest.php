<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Youandme\Auth\Events\UserRegistered;
use Youandme\Auth\Models\User;
use Youandme\Auth\Support\AppleTokenVerifierInterface;

test('apple sign-in creates a user with the relay email and returns 201', function (): void {
    Event::fake([UserRegistered::class]);

    $this->mock(AppleTokenVerifierInterface::class)
        ->shouldReceive('verify')
        ->andReturn([
            'sub' => 'apple-abc',
            'email' => 'relay@privaterelay.appleid.com',
            'email_verified' => true,
            'name' => null,
        ]);

    $response = $this->postJson('/api/v1/auth/apple', ['id_token' => 'fake-token']);

    $response->assertCreated()
        ->assertJsonPath('user.email', 'relay@privaterelay.appleid.com')
        ->assertJsonPath('user.is_apple_linked', true)
        ->assertJsonPath('user.is_google_linked', false);

    expect(User::where('apple_id', 'apple-abc')->exists())->toBeTrue();
    Event::assertDispatched(UserRegistered::class);
});

test('apple sign-in with a null email finds the user by apple id', function (): void {
    $user = createUserWithCouple([
        'email' => 'real@example.com',
        'apple_id' => 'apple-555',
    ]);

    $this->mock(AppleTokenVerifierInterface::class)
        ->shouldReceive('verify')
        ->andReturn([
            'sub' => 'apple-555',
            'email' => null,
            'email_verified' => false,
            'name' => null,
        ]);

    $this->postJson('/api/v1/auth/apple', ['id_token' => 'fake-token'])
        ->assertOk()
        ->assertJsonPath('user.email', 'real@example.com');

    expect(User::count())->toBe(1);
});

test('apple first sign-in without an email returns 422', function (): void {
    $this->mock(AppleTokenVerifierInterface::class)
        ->shouldReceive('verify')
        ->andReturn([
            'sub' => 'apple-brand-new',
            'email' => null,
            'email_verified' => false,
            'name' => null,
        ]);

    $this->postJson('/api/v1/auth/apple', ['id_token' => 'fake-token'])
        ->assertStatus(422);
});

test('an apple address the provider has not verified does not sign into an existing account', function (): void {
    $victim = createUserWithCouple(['email' => 'ofiara@example.com']);

    $this->mock(AppleTokenVerifierInterface::class)
        ->shouldReceive('verify')
        ->andReturn([
            'sub' => 'apple-attacker',
            'email' => 'ofiara@example.com',
            'email_verified' => false,
            'name' => null,
        ]);

    $this->postJson('/api/v1/auth/apple', ['id_token' => 'fake-token'])
        ->assertStatus(422);

    expect($victim->fresh()->apple_id)->toBeNull()
        ->and(User::count())->toBe(1)
        ->and(DB::table('personal_access_tokens')->count())->toBe(0);
});

test('a second apple account claiming a linked address is refused, not signed in', function (): void {
    $user = createUserWithCouple(['email' => 'zajete@example.com', 'apple_id' => 'apple-first']);

    $this->mock(AppleTokenVerifierInterface::class)
        ->shouldReceive('verify')
        ->andReturn([
            'sub' => 'apple-second',
            'email' => 'zajete@example.com',
            'email_verified' => true,
            'name' => null,
        ]);

    $this->postJson('/api/v1/auth/apple', ['id_token' => 'fake-token'])
        ->assertStatus(409);

    expect($user->fresh()->apple_id)->toBe('apple-first')
        ->and(DB::table('personal_access_tokens')->count())->toBe(0);
});
