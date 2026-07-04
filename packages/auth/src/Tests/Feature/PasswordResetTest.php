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

test('reset request uses custom scheme deep link when configured', function (): void {
    config(['app.mobile_deep_link_scheme' => 'jaity']);
    Event::fake([PasswordResetRequested::class]);

    $user = User::factory()->create();
    $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])
        ->assertOk();

    Event::assertDispatched(
        PasswordResetRequested::class,
        fn (PasswordResetRequested $e): bool => str_starts_with($e->resetUrl, 'jaity://reset-password?token=')
            && str_contains($e->resetUrl, 'email='.urlencode($user->email)),
    );
});

test('reset request falls back to web URL when scheme is empty', function (): void {
    config(['app.mobile_deep_link_scheme' => null]);
    Event::fake([PasswordResetRequested::class]);

    $user = User::factory()->create();
    $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])
        ->assertOk();

    Event::assertDispatched(
        PasswordResetRequested::class,
        fn (PasswordResetRequested $e): bool => str_starts_with($e->resetUrl, config('app.url').'/reset-password?token=')
            && str_contains($e->resetUrl, 'email='.urlencode($user->email)),
    );
});
