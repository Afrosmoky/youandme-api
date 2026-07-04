<?php

use App\Models\Category;
use App\Models\GameSession;
use App\Models\Question;
use Youandme\Auth\Models\User;
use Laravel\Sanctum\Sanctum;

test('authenticated user can start a session with a category', function (): void {
    $category = Category::factory()->create(['slug' => 'na_poznanie', 'name' => 'Na poznanie']);
    Question::factory()->count(5)->create(['category_id' => $category->id]);

    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/sessions/start', ['category_slug' => 'na_poznanie']);

    $response->assertCreated()
        ->assertJsonPath('session.category.slug', 'na_poznanie')
        ->assertJsonPath('session.category.name', 'Na poznanie')
        ->assertJsonPath('session.current_index', 0)
        ->assertJsonPath('session.mode', 'local');

    expect($response->json('session.remaining_count'))->toBeGreaterThan(0);
});

test('authenticated user can start a session in mix mode', function (): void {
    Question::factory()->count(5)->create();

    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/sessions/start', ['category_slug' => null])
        ->assertCreated()
        ->assertJsonPath('session.category', null);
});

test('starting a session does not expose internal remaining_ids', function (): void {
    Question::factory()->count(3)->create();
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/sessions/start', ['category_slug' => null]);

    expect($response->json('session'))->not->toHaveKey('remaining_ids');
    expect($response->json('session.state'))->toBeNull();
});

test('returns 409 with the current session when one is already active', function (): void {
    Question::factory()->count(5)->create();
    Sanctum::actingAs(User::factory()->create());

    $first = $this->postJson('/api/v1/sessions/start', ['category_slug' => null])->assertCreated();

    $this->postJson('/api/v1/sessions/start', ['category_slug' => null])
        ->assertStatus(409)
        ->assertJsonPath('session.ulid', $first->json('session.ulid'));

    expect(GameSession::count())->toBe(1);
});

test('returns 422 when the category pool is exhausted', function (): void {
    $category = Category::factory()->create(['slug' => 'intymnosc', 'name' => 'Intymność']);
    $questions = Question::factory()->count(3)->create(['category_id' => $category->id]);

    $user = User::factory()->create();
    $user->activeCouple->seenQuestions()->attach(
        $questions->mapWithKeys(fn ($q) => [$q->id => ['seen_at' => now()]])->all()
    );
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/sessions/start', ['category_slug' => 'intymnosc'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Pula pytań w tej kategorii została wyczerpana. Spróbuj innej kategorii lub trybu mix.');
});

test('returns 422 when the category does not exist', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/sessions/start', ['category_slug' => 'nieistnieje'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_slug');
});

test('starting a session requires authentication', function (): void {
    $this->postJson('/api/v1/sessions/start', ['category_slug' => null])->assertUnauthorized();
});
