<?php

use App\Models\GameSession;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('returns the active session when one exists', function (): void {
    $user = User::factory()->create();
    $session = GameSession::factory()->for($user->activeCouple)->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/sessions/active')
        ->assertOk()
        ->assertJsonPath('session.ulid', $session->ulid);
});

test('returns 404 when there is no active session', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/sessions/active')->assertNotFound();
});

test('returns 404 when the only session has ended', function (): void {
    $user = User::factory()->create();
    GameSession::factory()->for($user->activeCouple)->create(['ended_at' => now()]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/sessions/active')->assertNotFound();
});

test('listing the active session requires authentication', function (): void {
    $this->getJson('/api/v1/sessions/active')->assertUnauthorized();
});
