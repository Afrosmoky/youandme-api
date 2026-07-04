<?php

use App\Modules\Game\Models\GameSession;
use Youandme\Auth\Models\User;
use Laravel\Sanctum\Sanctum;

test('ending a session sets ended_at and returns 204', function (): void {
    $user = createUserWithCouple();
    $session = GameSession::factory()->for(activeCoupleOf($user))->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/sessions/{$session->ulid}/end")->assertNoContent();

    expect($session->fresh()->ended_at)->not->toBeNull();
});

test('returns 410 when the session is already ended', function (): void {
    $user = createUserWithCouple();
    $session = GameSession::factory()->for(activeCoupleOf($user))->create(['ended_at' => now()]);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/sessions/{$session->ulid}/end")->assertStatus(410);
});

test('returns 403 when the session belongs to a different couple', function (): void {
    $owner = createUserWithCouple();
    $session = GameSession::factory()->for(activeCoupleOf($owner))->create();

    Sanctum::actingAs(createUserWithCouple());

    $this->postJson("/api/v1/sessions/{$session->ulid}/end")->assertForbidden();

    expect($session->fresh()->ended_at)->toBeNull();
});

test('ending a session requires authentication', function (): void {
    $session = GameSession::factory()->create();

    $this->postJson("/api/v1/sessions/{$session->ulid}/end")->assertUnauthorized();
});
