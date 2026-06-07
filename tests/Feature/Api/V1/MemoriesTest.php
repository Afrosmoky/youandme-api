<?php

use App\Models\Memory;
use App\Models\Question;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('create memory returns 201 and persists', function (): void {
    $user = User::factory()->create();
    $question = Question::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/memories', [
        'question_ulid' => $question->ulid,
        'answer_a' => 'Test odpowiedzi z polskimi znakami ąęść.',
        'answered_at' => '2026-05-21T10:00:00Z',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'memory' => [
                'ulid',
                'question' => ['ulid', 'body', 'type', 'category', 'tags'],
                'origin',
                'answer_a',
                'answer_b',
                'player_a_name',
                'player_b_name',
                'answered_at',
                'created_at',
            ],
        ])
        ->assertJsonPath('memory.question.ulid', $question->ulid)
        ->assertJsonPath('memory.answer_a', 'Test odpowiedzi z polskimi znakami ąęść.');

    expect(Memory::where('user_id', $user->id)
        ->where('question_id', $question->id)
        ->exists())->toBeTrue();
});

test('store memory sets couple_id from the active couple', function (): void {
    $user = User::factory()->create();
    $question = Question::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/memories', [
        'question_ulid' => $question->ulid,
        'answer_a' => 'Odpowiedź.',
        'answered_at' => '2026-05-21T10:00:00Z',
    ])->assertCreated();

    $memory = Memory::where('user_id', $user->id)->firstOrFail();
    expect($memory->couple_id)->toBe($user->active_couple_id);
});

test('store memory snapshots player names', function (): void {
    $user = User::factory()->create(['nickname' => 'ola']);
    $user->activeCouple->update(['partner_name_local' => 'Tomek']);
    $question = Question::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/memories', [
        'question_ulid' => $question->ulid,
        'answer_a' => 'Odpowiedź.',
        'answered_at' => '2026-05-21T10:00:00Z',
    ])
        ->assertCreated()
        ->assertJsonPath('memory.player_a_name', 'ola')
        ->assertJsonPath('memory.player_b_name', 'Tomek');
});

test('store memory persists answer_b when provided', function (): void {
    $user = User::factory()->create();
    $question = Question::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/memories', [
        'question_ulid' => $question->ulid,
        'answer_a' => 'Moja odpowiedź.',
        'answer_b' => 'Odpowiedź partnera.',
        'answered_at' => '2026-05-21T10:00:00Z',
    ])
        ->assertCreated()
        ->assertJsonPath('memory.answer_a', 'Moja odpowiedź.')
        ->assertJsonPath('memory.answer_b', 'Odpowiedź partnera.');
});

test('store memory defaults origin to session', function (): void {
    $user = User::factory()->create();
    $question = Question::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/memories', [
        'question_ulid' => $question->ulid,
        'answer_a' => 'Odpowiedź.',
        'answered_at' => '2026-05-21T10:00:00Z',
    ])
        ->assertCreated()
        ->assertJsonPath('memory.origin', 'session');
});

test('store memory requires answer_a', function (): void {
    $user = User::factory()->create();
    $question = Question::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/memories', [
        'question_ulid' => $question->ulid,
        'answered_at' => '2026-05-21T10:00:00Z',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('answer_a');
});

test('index returns only the couple memories in order', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $question = Question::factory()->create();

    $m1 = Memory::factory()->for($userA)->for($question)->create(['answered_at' => '2026-05-19T10:00:00Z']);
    $m2 = Memory::factory()->for($userA)->for($question)->create(['answered_at' => '2026-05-20T10:00:00Z']);
    $m3 = Memory::factory()->for($userA)->for($question)->create(['answered_at' => '2026-05-21T10:00:00Z']);
    Memory::factory()->for($userB)->for($question)->create(['answered_at' => '2026-05-21T11:00:00Z']);

    Sanctum::actingAs($userA);

    $this->getJson('/api/v1/memories')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.ulid', $m3->ulid)
        ->assertJsonPath('data.1.ulid', $m2->ulid)
        ->assertJsonPath('data.2.ulid', $m1->ulid)
        ->assertJsonPath('meta.per_page', 20);
});

test('create requires auth', function (): void {
    $question = Question::factory()->create();

    $response = $this->postJson('/api/v1/memories', [
        'question_ulid' => $question->ulid,
        'answer_a' => 'Test',
        'answered_at' => '2026-05-21T10:00:00Z',
    ]);

    $response->assertUnauthorized();
});
