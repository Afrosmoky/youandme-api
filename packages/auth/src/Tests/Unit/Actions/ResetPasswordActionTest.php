<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Youandme\Auth\Actions\ResetPasswordAction;
use Youandme\Auth\Events\PasswordChanged;
use Youandme\Auth\Models\User;

test('handle sets a new password for a valid token', function (): void {
    $user = User::factory()->create(['email' => 'reset@example.com', 'password' => 'stare-haslo-123']);
    $token = Password::createToken($user);

    $status = ResetPasswordAction::run('reset@example.com', 'nowe-haslo-456', $token);

    expect($status)->toBe(Password::PasswordReset);
    expect(Hash::check('nowe-haslo-456', $user->fresh()->password))->toBeTrue();
});

test('handle dispatches PasswordChanged on success', function (): void {
    Event::fake([PasswordChanged::class]);
    $user = User::factory()->create(['email' => 'reset2@example.com', 'password' => 'stare-haslo-123']);
    $token = Password::createToken($user);

    ResetPasswordAction::run('reset2@example.com', 'nowe-haslo-456', $token);

    Event::assertDispatched(PasswordChanged::class);
});

test('handle returns an error status and keeps the password for an invalid token', function (): void {
    $user = User::factory()->create(['email' => 'reset3@example.com', 'password' => 'stare-haslo-123']);

    $status = ResetPasswordAction::run('reset3@example.com', 'nowe-haslo-456', 'invalid-token');

    expect($status)->not->toBe(Password::PasswordReset);
    expect(Hash::check('stare-haslo-123', $user->fresh()->password))->toBeTrue();
});
