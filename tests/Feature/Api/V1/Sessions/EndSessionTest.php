<?php

use App\Models\GameSession;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('ending a session sets ended_at and returns 204', function (): void {
    $user = User::factory()->create();
    $session = GameSession::factory()->for($user->activeCouple)->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/sessions/{$session->ulid}/end")->assertNoContent();

    expect($session->fresh()->ended_at)->not->toBeNull();
});

test('returns 410 when the session is already ended', function (): void {
    $user = User::factory()->create();
    $session = GameSession::factory()->for($user->activeCouple)->create(['ended_at' => now()]);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/sessions/{$session->ulid}/end")->assertStatus(410);
});

test('returns 403 when the session belongs to a different couple', function (): void {
    $owner = User::factory()->create();
    $session = GameSession::factory()->for($owner->activeCouple)->create();

    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/sessions/{$session->ulid}/end")->assertForbidden();

    expect($session->fresh()->ended_at)->toBeNull();
});

test('ending a session requires authentication', function (): void {
    $session = GameSession::factory()->create();

    $this->postJson("/api/v1/sessions/{$session->ulid}/end")->assertUnauthorized();
});
