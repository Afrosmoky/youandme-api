<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Memories\Models\Memory;
use Laravel\Sanctum\Sanctum;

/*
 | GET /memories/{ulid} — the memory card screen, opened from the list or from an
 | anniversary push deep link.
 */

test('a couple can open one of its own memories', function (): void {
    $user = createUserWithCouple();
    $question = Question::factory()->create();
    $memory = Memory::factory()->for($user)->for($question)->create([
        'answer_a' => 'Moja',
        'answer_b' => 'Partnera',
        'is_favorite' => true,
    ]);
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/memories/{$memory->ulid}")
        ->assertOk()
        ->assertJsonPath('memory.ulid', $memory->ulid)
        ->assertJsonPath('memory.answer_a', 'Moja')
        ->assertJsonPath('memory.answer_b', 'Partnera')
        ->assertJsonPath('memory.is_favorite', true)
        // Same shape as the list — the card screen and the list row read the same
        // resource, question included.
        ->assertJsonPath('memory.question.ulid', $question->ulid)
        ->assertJsonPath('memory.question.body', $question->body);
});

test('opening another couple memory is forbidden', function (): void {
    $user = createUserWithCouple();
    $stranger = createUserWithCouple();
    $theirs = Memory::factory()->for($stranger)->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/memories/{$theirs->ulid}")->assertForbidden();
});

test('a deleted memory can no longer be opened', function (): void {
    $user = createUserWithCouple();
    $memory = Memory::factory()->for($user)->create();
    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/memories/{$memory->ulid}")->assertNoContent();

    $this->getJson("/api/v1/memories/{$memory->ulid}")->assertNotFound();
});

test('opening a memory requires authentication', function (): void {
    $memory = Memory::factory()->for(createUserWithCouple())->create();

    $this->getJson("/api/v1/memories/{$memory->ulid}")->assertUnauthorized();
});
