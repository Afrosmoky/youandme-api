<?php

use App\Modules\Game\Models\GameSession;
use Youandme\Auth\Models\User;
use Laravel\Sanctum\Sanctum;

test('returns the active session when one exists', function (): void {
    $user = createUserWithCouple();
    $session = GameSession::factory()->for(activeCoupleOf($user))->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/sessions/active')
        ->assertOk()
        ->assertJsonPath('session.ulid', $session->ulid);
});

test('returns 404 when there is no active session', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    $this->getJson('/api/v1/sessions/active')->assertNotFound();
});

test('returns 404 when the only session has ended', function (): void {
    $user = createUserWithCouple();
    GameSession::factory()->for(activeCoupleOf($user))->create(['ended_at' => now()]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/sessions/active')->assertNotFound();
});

test('listing the active session requires authentication', function (): void {
    $this->getJson('/api/v1/sessions/active')->assertUnauthorized();
});
