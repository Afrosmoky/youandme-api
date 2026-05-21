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
        'answer' => 'Test odpowiedzi z polskimi znakami ąęść.',
        'answered_at' => '2026-05-21T10:00:00Z',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'memory' => [
                'ulid',
                'question' => ['ulid', 'body', 'type'],
                'answer',
                'answered_at',
                'created_at',
            ],
        ])
        ->assertJsonPath('memory.question.ulid', $question->ulid)
        ->assertJsonPath('memory.answer', 'Test odpowiedzi z polskimi znakami ąęść.');

    expect(Memory::where('user_id', $user->id)
        ->where('question_id', $question->id)
        ->exists())->toBeTrue();
});

test('index returns user memories in order', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $question = Question::factory()->create();

    $m1 = Memory::factory()->for($userA)->for($question)->create(['answered_at' => '2026-05-19T10:00:00Z']);
    $m2 = Memory::factory()->for($userA)->for($question)->create(['answered_at' => '2026-05-20T10:00:00Z']);
    $m3 = Memory::factory()->for($userA)->for($question)->create(['answered_at' => '2026-05-21T10:00:00Z']);
    Memory::factory()->for($userB)->for($question)->create(['answered_at' => '2026-05-21T11:00:00Z']);

    Sanctum::actingAs($userA);

    $response = $this->getJson('/api/v1/memories');

    $response->assertOk()
        ->assertJsonCount(3, 'memories')
        ->assertJsonPath('memories.0.ulid', $m3->ulid)
        ->assertJsonPath('memories.1.ulid', $m2->ulid)
        ->assertJsonPath('memories.2.ulid', $m1->ulid);
});

test('create requires auth', function (): void {
    $question = Question::factory()->create();

    $response = $this->postJson('/api/v1/memories', [
        'question_ulid' => $question->ulid,
        'answer' => 'Test',
        'answered_at' => '2026-05-21T10:00:00Z',
    ]);

    $response->assertUnauthorized();
});
