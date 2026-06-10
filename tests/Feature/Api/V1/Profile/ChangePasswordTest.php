<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

test('authenticated user can change password', function (): void {
    $user = User::factory()->create(['password' => 'stare-haslo-123']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/me/change-password', [
        'current_password' => 'stare-haslo-123',
        'new_password' => 'nowe-haslo-456',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Hasło zostało zmienione.');

    expect(Hash::check('nowe-haslo-456', $user->fresh()->password))->toBeTrue();
});

test('returns 422 when the current password is wrong', function (): void {
    $user = User::factory()->create(['password' => 'stare-haslo-123']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/me/change-password', [
        'current_password' => 'zle-haslo',
        'new_password' => 'nowe-haslo-456',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('current_password');

    expect(Hash::check('stare-haslo-123', $user->fresh()->password))->toBeTrue();
});

test('returns 422 when the new password is too short', function (): void {
    $user = User::factory()->create(['password' => 'stare-haslo-123']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/me/change-password', [
        'current_password' => 'stare-haslo-123',
        'new_password' => 'krotkie',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('new_password');
});

test('returns 422 when the new password equals the current one', function (): void {
    $user = User::factory()->create(['password' => 'stare-haslo-123']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/me/change-password', [
        'current_password' => 'stare-haslo-123',
        'new_password' => 'stare-haslo-123',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('new_password');
});

test('returns 422 when the new password is missing', function (): void {
    $user = User::factory()->create(['password' => 'stare-haslo-123']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/me/change-password', [
        'current_password' => 'stare-haslo-123',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('new_password');
});

test('revokes other tokens but keeps the current one', function (): void {
    $user = User::factory()->create(['password' => 'stare-haslo-123']);
    $user->createToken('device-1');
    $current = $user->createToken('device-2')->plainTextToken;
    $user->createToken('device-3');

    expect($user->tokens()->count())->toBe(3);

    $this->withToken($current)->postJson('/api/v1/me/change-password', [
        'current_password' => 'stare-haslo-123',
        'new_password' => 'nowe-haslo-456',
    ])->assertOk();

    expect($user->tokens()->count())->toBe(1);
});

test('stores the new password hashed, not in plaintext', function (): void {
    $user = User::factory()->create(['password' => 'stare-haslo-123']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/me/change-password', [
        'current_password' => 'stare-haslo-123',
        'new_password' => 'nowe-haslo-456',
    ])->assertOk();

    $stored = $user->fresh()->password;
    expect($stored)->not->toBe('nowe-haslo-456');
    expect(Hash::check('nowe-haslo-456', $stored))->toBeTrue();
});

test('change password requires authentication', function (): void {
    $this->postJson('/api/v1/me/change-password', [
        'current_password' => 'stare-haslo-123',
        'new_password' => 'nowe-haslo-456',
    ])->assertUnauthorized();
});
