<?php

use App\Models\Category;
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

test('next includes category when the question has one', function (): void {
    $category = Category::factory()->create(['slug' => 'na_poznanie', 'name' => 'Na poznanie']);
    Question::factory()->create(['category_id' => $category->id]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/questions/next')
        ->assertOk()
        ->assertJsonPath('question.category.slug', 'na_poznanie')
        ->assertJsonPath('question.category.name', 'Na poznanie');
});

test('next includes tags', function (): void {
    Question::factory()->create(['tags' => ['libido']]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/questions/next')
        ->assertOk()
        ->assertJsonPath('question.tags', ['libido']);
});

test('next returns null category when the question has none', function (): void {
    Question::factory()->create(['category_id' => null]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/questions/next')
        ->assertOk()
        ->assertJsonPath('question.category', null)
        ->assertJsonPath('question.tags', []);
});
