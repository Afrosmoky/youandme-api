<?php

use App\Models\User;

test('user can register and receive token', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'ola@example.com',
        'password' => 'tajne-haslo-123',
        'nickname' => 'ola_test',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'user' => ['ulid', 'email', 'nickname', 'timezone', 'locale'],
            'token',
        ])
        ->assertJsonPath('user.email', 'ola@example.com')
        ->assertJsonPath('user.nickname', 'ola_test')
        // Regresja: $attributes w User model + DB default — patrz CLAUDE.md
        ->assertJsonPath('user.timezone', 'Europe/Warsaw')
        ->assertJsonPath('user.locale', 'pl');

    expect(User::where('email', 'ola@example.com')->exists())->toBeTrue();
});

test('registration auto-creates a couple returned in the response', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'ola@example.com',
        'password' => 'tajne-haslo-123',
        'nickname' => 'ola_test',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'couple' => [
                'ulid',
                'partner_name_local',
                'streak_current',
                'streak_longest',
                'daily_push_hour',
                'relationship_started_on',
                'created_at',
            ],
        ])
        ->assertJsonPath('couple.partner_name_local', null)
        ->assertJsonPath('couple.streak_current', 0)
        ->assertJsonPath('couple.daily_push_hour', 20);

    $user = User::where('email', 'ola@example.com')->firstOrFail();
    expect($user->active_couple_id)->not->toBeNull();
    expect($user->activeCouple->user_a_id)->toBe($user->id);
});

test('duplicate email returns 422', function (): void {
    User::factory()->create(['email' => 'ola@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'ola@example.com',
        'password' => 'tajne-haslo-123',
        'nickname' => 'inny_nick',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('register is throttled after 10 attempts within a minute', function (): void {
    User::factory()->create(['email' => 'ola@example.com']);

    // Duplicate-email requests still count against the throttle (the middleware
    // increments before the controller runs).
    $payload = ['email' => 'ola@example.com', 'password' => 'tajne-haslo-123', 'nickname' => 'inny_nick'];

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/auth/register', $payload)->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/register', $payload)
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});
