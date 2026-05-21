<?php

use App\Models\Question;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('next requires auth', function (): void {
    $response = $this->getJson('/api/v1/questions/next');

    $response->assertUnauthorized();
});

test('next returns random session question', function (): void {
    Question::factory()->count(3)->create();
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/v1/questions/next');

    $response->assertOk()
        ->assertJsonStructure([
            'question' => ['ulid', 'body', 'type'],
        ])
        ->assertJsonPath('question.type', 'session');

    expect($response->json('question.body'))->toBeString()->not->toBe('');
    expect(strlen($response->json('question.ulid')))->toBe(26);
});
