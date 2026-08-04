<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Memories\Models\Memory;
use Laravel\Sanctum\Sanctum;

/*
 | Hearting a memory (P9 Slice A). Two idempotent verbs, the couple from the
 | token, and a filtered read of the same list.
 */

test('a memory can be hearted and un-hearted', function (): void {
    $user = createUserWithCouple();
    $memory = Memory::factory()->for($user)->for(Question::factory()->create())->create();
    Sanctum::actingAs($user);

    $this->putJson("/api/v1/memories/{$memory->ulid}/favorite")
        ->assertOk()
        ->assertJsonPath('memory.ulid', $memory->ulid)
        ->assertJsonPath('memory.is_favorite', true);

    expect($memory->fresh()->is_favorite)->toBeTrue();

    $this->deleteJson("/api/v1/memories/{$memory->ulid}/favorite")
        ->assertOk()
        ->assertJsonPath('memory.is_favorite', false);

    expect($memory->fresh()->is_favorite)->toBeFalse();
});

test('hearting twice leaves the memory hearted', function (): void {
    $user = createUserWithCouple();
    $memory = Memory::factory()->for($user)->for(Question::factory()->create())->create();
    Sanctum::actingAs($user);

    $this->putJson("/api/v1/memories/{$memory->ulid}/favorite")->assertOk();
    $this->putJson("/api/v1/memories/{$memory->ulid}/favorite")->assertOk();

    // The verb states the target, so a retry cannot flip it back.
    expect($memory->fresh()->is_favorite)->toBeTrue();
});

test('a memory is not a favorite by default', function (): void {
    $user = createUserWithCouple();
    Memory::factory()->for($user)->for(Question::factory()->create())->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/memories')
        ->assertOk()
        ->assertJsonPath('data.0.is_favorite', false);
});

test('favorites=1 narrows the list to hearted memories, newest first', function (): void {
    $user = createUserWithCouple();
    $question = Question::factory()->create();

    $older = Memory::factory()->for($user)->for($question)->create([
        'answered_at' => '2026-05-19T10:00:00Z',
        'is_favorite' => true,
    ]);
    $newer = Memory::factory()->for($user)->for($question)->create([
        'answered_at' => '2026-05-21T10:00:00Z',
        'is_favorite' => true,
    ]);
    Memory::factory()->for($user)->for($question)->create(['answered_at' => '2026-05-20T10:00:00Z']);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/memories?favorites=1')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.ulid', $newer->ulid)
        ->assertJsonPath('data.1.ulid', $older->ulid)
        ->assertJsonPath('meta.per_page', 20);

    // Without the filter the full history is still there.
    $this->getJson('/api/v1/memories')->assertOk()->assertJsonCount(3, 'data');
});

test('a deleted memory never shows up among the favorites', function (): void {
    $user = createUserWithCouple();
    $memory = Memory::factory()->for($user)->for(Question::factory()->create())->create(['is_favorite' => true]);
    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/memories/{$memory->ulid}")->assertNoContent();

    $this->getJson('/api/v1/memories?favorites=1')->assertOk()->assertJsonCount(0, 'data');
});

test('hearting another couple memory is forbidden', function (): void {
    $user = createUserWithCouple();
    $stranger = createUserWithCouple();
    $theirs = Memory::factory()->for($stranger)->for(Question::factory()->create())->create();

    Sanctum::actingAs($user);

    $this->putJson("/api/v1/memories/{$theirs->ulid}/favorite")->assertForbidden();
    $this->deleteJson("/api/v1/memories/{$theirs->ulid}/favorite")->assertForbidden();

    expect($theirs->fresh()->is_favorite)->toBeFalse();
});

test('hearting requires authentication', function (): void {
    $memory = Memory::factory()->for(createUserWithCouple())->create();

    $this->putJson("/api/v1/memories/{$memory->ulid}/favorite")->assertUnauthorized();
});
