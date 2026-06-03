<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('forgot password sends a reset link', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ola@example.com']);

    $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'ola@example.com',
    ])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class);
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
