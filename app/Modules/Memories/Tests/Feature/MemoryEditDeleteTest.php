<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Memories\Models\Memory;
use Laravel\Sanctum\Sanctum;

/*
 | Editing and removing a memory (P9 Slice A). The couple comes from the token,
 | removal is a soft delete, and the two intentions on `memories` — the list that
 | hides deleted rows and the lifetime counter that keeps counting them — are
 | pinned here.
 */

test('both answers can be rewritten without touching the history around them', function (): void {
    $user = createUserWithCouple();
    $memory = Memory::factory()->for($user)->for(Question::factory()->create())->create([
        'answer_a' => 'Stara moja',
        'answer_b' => 'Stara partnera',
        'player_a_name' => 'ola',
        'player_b_name' => 'Tomek',
        'answered_at' => '2026-05-20T10:00:00Z',
    ]);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/memories/{$memory->ulid}", [
        'answer_a' => 'Nowa moja',
        'answer_b' => 'Nowa partnera',
    ])
        ->assertOk()
        ->assertJsonPath('memory.answer_a', 'Nowa moja')
        ->assertJsonPath('memory.answer_b', 'Nowa partnera')
        // Snapshots and the moment stay as they were written.
        ->assertJsonPath('memory.player_a_name', 'ola')
        ->assertJsonPath('memory.player_b_name', 'Tomek')
        ->assertJsonPath('memory.answered_at', '2026-05-20T10:00:00Z');
});

test('an omitted answer_b clears the partner answer', function (): void {
    $user = createUserWithCouple();
    $memory = Memory::factory()->for($user)->create(['answer_b' => 'Było']);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/memories/{$memory->ulid}", ['answer_a' => 'Zostaje'])
        ->assertOk()
        ->assertJsonPath('memory.answer_b', null);

    expect($memory->fresh()->answer_b)->toBeNull();
});

test('an edit without an answer is rejected', function (): void {
    $user = createUserWithCouple();
    $memory = Memory::factory()->for($user)->create(['answer_a' => 'Oryginał']);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/memories/{$memory->ulid}", ['answer_b' => 'Tylko partnera'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('answer_a');

    expect($memory->fresh()->answer_a)->toBe('Oryginał');
});

test('editing another couple memory is forbidden', function (): void {
    $user = createUserWithCouple();
    $stranger = createUserWithCouple();
    $theirs = Memory::factory()->for($stranger)->create(['answer_a' => 'Ich odpowiedź']);

    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/memories/{$theirs->ulid}", ['answer_a' => 'Podmieniona'])
        ->assertForbidden();

    expect($theirs->fresh()->answer_a)->toBe('Ich odpowiedź');
});

test('a deleted memory leaves the list but its row survives', function (): void {
    $user = createUserWithCouple();
    $memory = Memory::factory()->for($user)->for(Question::factory()->create())->create();
    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/memories/{$memory->ulid}")->assertNoContent();

    // Soft, not hard: the couple may ask for it back, and the P10 history rebuild
    // reads deleted rows too (a deleted memory does not un-play its card).
    expect($memory->fresh()->deleted_at)->not->toBeNull();
    expect(Memory::withTrashed()->count())->toBe(1);

    $this->getJson('/api/v1/memories')->assertOk()->assertJsonCount(0, 'data');
});

test('deleting the same memory twice is a 404, not a second delete', function (): void {
    $user = createUserWithCouple();
    $memory = Memory::factory()->for($user)->create();
    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/memories/{$memory->ulid}")->assertNoContent();
    $this->deleteJson("/api/v1/memories/{$memory->ulid}")->assertNotFound();
});

test('a deleted memory can no longer be edited or hearted', function (): void {
    $user = createUserWithCouple();
    $memory = Memory::factory()->for($user)->create();
    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/memories/{$memory->ulid}")->assertNoContent();

    $this->patchJson("/api/v1/memories/{$memory->ulid}", ['answer_a' => 'Cokolwiek'])->assertNotFound();
    $this->putJson("/api/v1/memories/{$memory->ulid}/favorite")->assertNotFound();
});

test('deleting another couple memory is forbidden', function (): void {
    $user = createUserWithCouple();
    $stranger = createUserWithCouple();
    $theirs = Memory::factory()->for($stranger)->create();

    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/memories/{$theirs->ulid}")->assertForbidden();

    expect($theirs->fresh()->deleted_at)->toBeNull();
});

test('editing and deleting require authentication', function (): void {
    $memory = Memory::factory()->for(createUserWithCouple())->create();

    $this->patchJson("/api/v1/memories/{$memory->ulid}", ['answer_a' => 'X'])->assertUnauthorized();
    $this->deleteJson("/api/v1/memories/{$memory->ulid}")->assertUnauthorized();
});
