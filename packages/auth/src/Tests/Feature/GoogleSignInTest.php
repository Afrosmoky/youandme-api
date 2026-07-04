<?php

use Youandme\Auth\Events\UserRegistered;
use App\Models\Couple;
use Youandme\Auth\Models\User;
use Youandme\Auth\Support\GoogleTokenVerifierInterface;
use Youandme\Auth\Support\SocialTokenException;
use Illuminate\Support\Facades\Event;

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
        ->assertJsonPath('user.email', 'nowy@example.com');

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
    $user = User::factory()->create(['email' => 'stary@example.com']);

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
        ->assertJsonPath('couple.ulid', $user->activeCouple->ulid);
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
