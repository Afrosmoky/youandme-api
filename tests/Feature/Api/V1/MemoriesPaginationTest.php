<?php

use App\Models\Memory;
use App\Modules\Catalog\Models\Question;
use Youandme\Auth\Models\User;
use Laravel\Sanctum\Sanctum;

test('memories use a default per_page of 20', function (): void {
    $user = createUserWithCouple();
    $question = Question::factory()->create();
    Memory::factory()->for($user)->for($question)->count(25)->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/memories');

    $response->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.per_page', 20);

    expect($response->json('meta.next_cursor'))->not->toBeNull();
    expect($response->json('meta.prev_cursor'))->toBeNull();
});

test('per_page is respected and capped at 50', function (): void {
    $user = createUserWithCouple();
    $question = Question::factory()->create();
    Memory::factory()->for($user)->for($question)->count(10)->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/memories?per_page=5')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.per_page', 5);

    $this->getJson('/api/v1/memories?per_page=999')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 50);
});

test('the cursor walks through pages without overlap', function (): void {
    $user = createUserWithCouple();
    $question = Question::factory()->create();
    Memory::factory()->for($user)->for($question)->count(30)->create();
    Sanctum::actingAs($user);

    $first = $this->getJson('/api/v1/memories?per_page=10');
    $first->assertOk()->assertJsonCount(10, 'data');
    $cursor = $first->json('meta.next_cursor');
    expect($cursor)->not->toBeNull();

    $second = $this->getJson('/api/v1/memories?per_page=10&cursor='.$cursor);
    $second->assertOk()->assertJsonCount(10, 'data');

    $firstUlids = collect($first->json('data'))->pluck('ulid');
    $secondUlids = collect($second->json('data'))->pluck('ulid');
    expect($firstUlids->intersect($secondUlids)->all())->toBe([]);
});
