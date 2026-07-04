<?php

use Youandme\Auth\Models\User;

test('login response carries the current couple with partner name', function (): void {
    $user = User::factory()->create([
        'email' => 'ola@example.com',
        'password' => 'tajne-haslo-123',
    ]);
    $user->activeCouple->update(['partner_name_local' => 'Anna']);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'ola@example.com',
        'password' => 'tajne-haslo-123',
    ])
        ->assertOk()
        ->assertJsonStructure(['couple' => ['ulid', 'partner_name_local']])
        ->assertJsonPath('couple.partner_name_local', 'Anna');
});

test('login with correct password returns token', function (): void {
    User::factory()->create([
        'email' => 'ola@example.com',
        'password' => 'tajne-haslo-123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ola@example.com',
        'password' => 'tajne-haslo-123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'user' => ['ulid', 'email', 'nickname'],
            'token',
        ])
        ->assertJsonPath('user.email', 'ola@example.com');
});

test('login with wrong password returns 401', function (): void {
    User::factory()->create([
        'email' => 'ola@example.com',
        'password' => 'tajne-haslo-123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ola@example.com',
        'password' => 'zle-haslo',
    ]);

    $response->assertUnauthorized();
});

test('login is throttled after 6 attempts within a minute', function (): void {
    User::factory()->create([
        'email' => 'ola@example.com',
        'password' => 'tajne-haslo-123',
    ]);

    $payload = ['email' => 'ola@example.com', 'password' => 'zle-haslo'];

    // 6 attempts are allowed (each a 401), the 7th is blocked.
    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();
    }

    $this->postJson('/api/v1/auth/login', $payload)
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});
