<?php

use App\Models\Memory;
use App\Modules\Catalog\Models\Question;
use Youandme\Auth\Models\User;
use Laravel\Sanctum\Sanctum;

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

test('index exposes the refactored memory shape', function (): void {
    $user = User::factory()->create();
    $question = Question::factory()->create();
    Memory::factory()->for($user)->for($question)->create([
        'answer_a' => 'Moja',
        'answer_b' => 'Partnera',
        'player_a_name' => 'ola',
        'player_b_name' => 'Tomek',
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/memories')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                ['ulid', 'question', 'origin', 'answer_a', 'answer_b', 'player_a_name', 'player_b_name', 'answered_at'],
            ],
        ])
        ->assertJsonPath('data.0.answer_a', 'Moja')
        ->assertJsonPath('data.0.answer_b', 'Partnera')
        ->assertJsonPath('data.0.player_a_name', 'ola')
        ->assertJsonPath('data.0.player_b_name', 'Tomek');
});

test('listing memories requires authentication', function (): void {
    $this->getJson('/api/v1/memories')->assertUnauthorized();
});
