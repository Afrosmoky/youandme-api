<?php

use Youandme\Auth\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

test('registration sends a verification email', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', [
        'email' => 'ola@example.com',
        'password' => 'tajne-haslo-123',
        'nickname' => 'ola_test',
    ])->assertCreated();

    $user = User::where('email', 'ola@example.com')->firstOrFail();
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('verify-notification resends the verification email', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/auth/email/verify-notification')->assertStatus(202);

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('a valid signed link verifies the email', function (): void {
    $user = User::factory()->create();
    expect($user->hasVerifiedEmail())->toBeFalse();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('verified', true);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a link with a wrong hash is rejected', function (): void {
    $user = User::factory()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1('inny@example.com'),
    ]);

    $this->getJson($url)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verification-status reflects verification state and account age', function (): void {
    $user = User::factory()->create(['created_at' => now()->subDays(3)]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me/verification-status')
        ->assertOk()
        ->assertJsonPath('verified', false)
        ->assertJsonPath('days_since_registration', 3);

    $user->markEmailAsVerified();

    $this->getJson('/api/v1/me/verification-status')
        ->assertJsonPath('verified', true);
});
