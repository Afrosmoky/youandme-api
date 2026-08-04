<?php

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
        ->assertJsonPath('user.email', 'relay@privaterelay.appleid.com');

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
