<?php

use App\Modules\Catalog\Models\Category;
use App\Models\GameSession;
use App\Modules\Catalog\Models\Question;
use Youandme\Auth\Models\User;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;

/**
 * @param  array<int, int>|null  $questionIds
 * @return array{0: User, 1: GameSession, 2: Collection<int, Question>}
 */
function sessionWithQuestions(int $count = 3, int $currentIndex = 0, ?Category $category = null): array
{
    $user = User::factory()->create();
    $questions = Question::factory()->count($count)->create(
        $category !== null ? ['category_id' => $category->id] : []
    );

    $session = GameSession::factory()->for($user->activeCouple)->create([
        'category_id' => $category?->id,
        'state' => [
            'remaining_ids' => $questions->pluck('id')->all(),
            'current_index' => $currentIndex,
            'draft_answer' => '',
        ],
    ]);

    return [$user, $session, $questions];
}

test('next returns 422 when there is no active session', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/questions/next')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Najpierw rozpocznij sesję.');
});

test('next returns the question at the current index of the session', function (): void {
    [$user, , $questions] = sessionWithQuestions();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/questions/next')
        ->assertOk()
        ->assertJsonPath('question.ulid', $questions->first()->ulid);
});

test('next does not advance the current index', function (): void {
    [$user, $session] = sessionWithQuestions();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/questions/next')->assertOk()->assertJsonPath('session.current_index', 0);
    $this->getJson('/api/v1/questions/next')->assertOk()->assertJsonPath('session.current_index', 0);

    expect($session->fresh()->state['current_index'])->toBe(0);
});

test('next reports session_complete when the pool is exhausted', function (): void {
    [$user] = sessionWithQuestions(count: 2, currentIndex: 2);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/questions/next')
        ->assertOk()
        ->assertJsonPath('question', null)
        ->assertJsonPath('session_complete', true)
        ->assertJsonPath('session.remaining_count', 2);
});

test('next includes the question category and tags', function (): void {
    $category = Category::factory()->create(['slug' => 'intymnosc', 'name' => 'Intymność']);
    $user = User::factory()->create();
    $question = Question::factory()->create(['category_id' => $category->id, 'tags' => ['libido']]);
    GameSession::factory()->for($user->activeCouple)->create([
        'category_id' => $category->id,
        'state' => ['remaining_ids' => [$question->id], 'current_index' => 0, 'draft_answer' => ''],
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/questions/next')
        ->assertOk()
        ->assertJsonPath('question.category.slug', 'intymnosc')
        ->assertJsonPath('question.category.name', 'Intymność')
        ->assertJsonPath('question.tags', ['libido']);
});

test('next includes session metadata', function (): void {
    [$user] = sessionWithQuestions(count: 4);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/questions/next')
        ->assertOk()
        ->assertJsonStructure([
            'question' => ['ulid', 'body', 'type', 'category', 'tags'],
            'session' => ['ulid', 'current_index', 'remaining_count', 'cards_drawn_count', 'cards_saved_count'],
        ])
        ->assertJsonPath('session.remaining_count', 4);
});

test('next requires authentication', function (): void {
    $this->getJson('/api/v1/questions/next')->assertUnauthorized();
});
