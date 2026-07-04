<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Events\EmailVerificationRequested;
use Youandme\Auth\Models\User;

test('registration emits a verification request with a signed URL', function (): void {
    Event::fake([EmailVerificationRequested::class]);

    $this->postJson('/api/v1/auth/register', [
        'email' => 'ola@example.com',
        'password' => 'tajne-haslo-123',
        'nickname' => 'ola_test',
    ])->assertCreated();

    Event::assertDispatched(
        EmailVerificationRequested::class,
        fn (EmailVerificationRequested $e): bool => $e->email === 'ola@example.com'
            && str_contains($e->verificationUrl, '/api/v1/auth/email/verify/'),
    );
});

test('verify-notification re-emits a verification request', function (): void {
    Event::fake([EmailVerificationRequested::class]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/auth/email/verify-notification')->assertStatus(202);

    Event::assertDispatched(
        EmailVerificationRequested::class,
        fn (EmailVerificationRequested $e): bool => $e->email === $user->email,
    );
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
