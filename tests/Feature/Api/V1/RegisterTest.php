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
        ->assertJsonPath('user.nickname', 'ola_test');

    expect(User::where('email', 'ola@example.com')->exists())->toBeTrue();
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
