<?php

use App\Models\User;

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
