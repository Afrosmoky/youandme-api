<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Youandme\Auth\Events\PasswordResetRequested;
use Youandme\Auth\Models\User;

test('forgot password emits a reset request', function (): void {
    Event::fake([PasswordResetRequested::class]);
    $user = User::factory()->create(['email' => 'ola@example.com']);

    $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'ola@example.com',
    ])->assertOk();

    Event::assertDispatched(
        PasswordResetRequested::class,
        fn (PasswordResetRequested $e): bool => $e->email === 'ola@example.com',
    );
});

test('forgot password is throttled after 3 attempts within a minute', function (): void {
    Event::fake([PasswordResetRequested::class]);
    User::factory()->create(['email' => 'ola@example.com']);

    $payload = ['email' => 'ola@example.com'];

    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/auth/password/forgot', $payload)->assertOk();
    }

    $this->postJson('/api/v1/auth/password/forgot', $payload)
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

test('password can be reset with a valid token', function (): void {
    $user = User::factory()->create([
        'email' => 'ola@example.com',
        'password' => 'stare-haslo-123',
    ]);
    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'ola@example.com',
        'token' => $token,
        'password' => 'nowe-haslo-456',
    ])->assertOk();

    expect(Hash::check('nowe-haslo-456', $user->fresh()->password))->toBeTrue();
});

test('reset with an invalid token returns 422', function (): void {
    User::factory()->create(['email' => 'ola@example.com']);

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'ola@example.com',
        'token' => 'zupelnie-zly-token',
        'password' => 'nowe-haslo-456',
    ])->assertStatus(422);
});

test('the reset mail links to the handoff page, not to the custom scheme', function (): void {
    Event::fake([PasswordResetRequested::class]);

    $user = User::factory()->create();
    $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])
        ->assertOk();

    // https, because a jaity:// link in a mail body is not reliably clickable.
    // The page behind this URL is what hands the token to the app.
    Event::assertDispatched(
        PasswordResetRequested::class,
        fn (PasswordResetRequested $e): bool => str_starts_with($e->resetUrl, config('app.url').'/api/v1/auth/password/reset?')
            && str_contains($e->resetUrl, 'token=')
            && str_contains($e->resetUrl, 'email='.urlencode($user->email)),
    );
});

test('the handoff page carries the token into the app', function (): void {
    $this->get('/api/v1/auth/password/reset?token=abc123&email=ola%40example.com')
        ->assertOk()
        // escape: false - assertSee escapes what it is given, and the href in the
        // page is already HTML-escaped by Blade.
        ->assertSee('jaity://reset-password?token=abc123&amp;email=ola%40example.com', false);
});
