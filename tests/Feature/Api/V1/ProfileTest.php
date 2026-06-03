<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('GET /me returns the authenticated user profile', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonStructure([
            'user' => ['ulid', 'email', 'nickname', 'timezone', 'locale', 'email_verified_at', 'created_at'],
        ])
        ->assertJsonPath('user.ulid', $user->ulid);
});

test('GET /me requires authentication', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

test('PATCH /me updates nickname, timezone and locale', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', [
        'nickname' => 'nowy_nick',
        'timezone' => 'Europe/London',
        'locale' => 'en',
    ])
        ->assertOk()
        ->assertJsonPath('user.nickname', 'nowy_nick')
        ->assertJsonPath('user.timezone', 'Europe/London')
        ->assertJsonPath('user.locale', 'en');

    expect($user->fresh()->nickname)->toBe('nowy_nick');
});

test('PATCH /me allows keeping the current nickname', function (): void {
    $user = User::factory()->create(['nickname' => 'moj_nick']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['nickname' => 'moj_nick'])->assertOk();
});

test('PATCH /me rejects an invalid or reserved nickname', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['nickname' => 'WielkieLitery'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nickname');

    $this->patchJson('/api/v1/me', ['nickname' => 'admin'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nickname');
});

test('PATCH /me rejects an invalid timezone', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['timezone' => 'Mars/Phobos'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('timezone');
});

test('PATCH /me requires authentication', function (): void {
    $this->patchJson('/api/v1/me', ['locale' => 'en'])->assertUnauthorized();
});
