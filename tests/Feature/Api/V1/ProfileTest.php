<?php

use Laravel\Sanctum\Sanctum;

test('GET /me returns the authenticated user profile', function (): void {
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonStructure([
            'user' => ['ulid', 'email', 'nickname', 'timezone', 'locale', 'email_verified_at', 'created_at'],
        ])
        ->assertJsonPath('user.ulid', $user->ulid);
});

test('GET /me includes the active couple', function (): void {
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonStructure(['couple' => ['ulid', 'partner_name_local', 'daily_push_hour']])
        ->assertJsonPath('couple.ulid', activeCoupleOf($user)->ulid);
});

test('PATCH /me updates partner_name_local on the couple', function (): void {
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['partner_name_local' => 'Tomek'])
        ->assertOk()
        ->assertJsonPath('couple.partner_name_local', 'Tomek');

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('couple.partner_name_local', 'Tomek');

    expect(activeCoupleOf($user)->fresh()->partner_name_local)->toBe('Tomek');
});

test('PATCH /me rejects a too long partner_name_local', function (): void {
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['partner_name_local' => str_repeat('a', 61)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('partner_name_local');
});

test('GET /me requires authentication', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

test('PATCH /me updates nickname, timezone and locale', function (): void {
    $user = createUserWithCouple();
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
    $user = createUserWithCouple(['nickname' => 'moj_nick']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['nickname' => 'moj_nick'])->assertOk();
});

test('PATCH /me rejects an invalid or reserved nickname', function (): void {
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['nickname' => 'WielkieLitery'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nickname');

    $this->patchJson('/api/v1/me', ['nickname' => 'admin'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nickname');
});

test('PATCH /me rejects an invalid timezone', function (): void {
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['timezone' => 'Mars/Phobos'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('timezone');
});

test('PATCH /me requires authentication', function (): void {
    $this->patchJson('/api/v1/me', ['locale' => 'en'])->assertUnauthorized();
});
