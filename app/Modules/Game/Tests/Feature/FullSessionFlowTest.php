<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use Youandme\Auth\Models\User;
use Laravel\Sanctum\Sanctum;

function answerCurrentCard(): string
{
    $ulid = test()->getJson('/api/v1/questions/next')->assertOk()->json('question.ulid');

    test()->postJson('/api/v1/memories', [
        'question_ulid' => $ulid,
        'answer_a' => 'Odpowiedź na '.$ulid,
        'answered_at' => '2026-06-07T10:00:00Z',
    ])->assertCreated();

    return $ulid;
}

test('a full session lifecycle works end to end', function (): void {
    $user = createUserWithCouple();
    $category = Category::factory()->create(['slug' => 'na_poznanie', 'name' => 'Na poznanie']);
    Question::factory()->count(3)->create(['category_id' => $category->id]);
    Sanctum::actingAs($user);

    $start = $this->postJson('/api/v1/sessions/start', ['category_slug' => 'na_poznanie'])->assertCreated();
    $ulid = $start->json('session.ulid');
    expect($start->json('session.remaining_count'))->toBeGreaterThan(0);

    // Draw + save the first card.
    $q1 = $this->getJson('/api/v1/questions/next')->assertOk()->json('question.ulid');
    $save = $this->postJson('/api/v1/memories', [
        'question_ulid' => $q1,
        'answer_a' => 'Pierwsza odpowiedź.',
        'answered_at' => '2026-06-07T10:00:00Z',
    ])->assertCreated();
    expect($save->json('session.current_index'))->toBe(1);
    expect($save->json('session.cards_saved_count'))->toBe(1);

    // Draw + skip the second card.
    $q2 = $this->getJson('/api/v1/questions/next')->assertOk()->json('question.ulid');
    expect($q2)->not->toBe($q1);
    $skip = $this->postJson("/api/v1/sessions/{$ulid}/skip-current")->assertOk();
    expect($skip->json('session.current_index'))->toBe(2);
    expect($skip->json('session.cards_drawn_count'))->toBe(2);
    expect($skip->json('session.cards_saved_count'))->toBe(1);

    // Draw the third card, then end the session.
    $q3 = $this->getJson('/api/v1/questions/next')->assertOk()->json('question.ulid');
    expect([$q1, $q2])->not->toContain($q3);

    $this->postJson("/api/v1/sessions/{$ulid}/end")->assertNoContent();
    $this->getJson('/api/v1/sessions/active')->assertNotFound();

    // A new session in another category starts cleanly.
    Question::factory()->count(2)->create([
        'category_id' => Category::factory()->create(['slug' => 'randka', 'name' => 'Randka'])->id,
    ]);
    $this->postJson('/api/v1/sessions/start', ['category_slug' => 'randka'])->assertCreated();
});

test('seen questions never come back in a later session of the same category', function (): void {
    $user = createUserWithCouple();
    $category = Category::factory()->create(['slug' => 'na_poznanie', 'name' => 'Na poznanie']);
    Question::factory()->count(5)->create(['category_id' => $category->id]);
    Sanctum::actingAs($user);

    $start = $this->postJson('/api/v1/sessions/start', ['category_slug' => 'na_poznanie'])->assertCreated();
    $ulid = $start->json('session.ulid');

    // Answer the whole pool.
    for ($i = 0; $i < 5; $i++) {
        answerCurrentCard();
    }

    $this->getJson('/api/v1/questions/next')
        ->assertOk()
        ->assertJsonPath('session_complete', true);

    $this->postJson("/api/v1/sessions/{$ulid}/end")->assertNoContent();

    // Every question in the category is now seen — the pool is exhausted.
    $this->postJson('/api/v1/sessions/start', ['category_slug' => 'na_poznanie'])
        ->assertStatus(422);

    expect(activeCoupleOf($user)->seenQuestions()->count())->toBe(5);
});

test('an interrupted session resumes from its current index', function (): void {
    $user = createUserWithCouple();
    $category = Category::factory()->create(['slug' => 'na_poznanie', 'name' => 'Na poznanie']);
    Question::factory()->count(5)->create(['category_id' => $category->id]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/sessions/start', ['category_slug' => 'na_poznanie'])->assertCreated();

    answerCurrentCard();
    answerCurrentCard();

    // Simulate an app restart: read the active session.
    $this->getJson('/api/v1/sessions/active')
        ->assertOk()
        ->assertJsonPath('session.current_index', 2);

    // The next draw continues where we left off without moving the index.
    $this->getJson('/api/v1/questions/next')
        ->assertOk()
        ->assertJsonPath('session.current_index', 2)
        ->assertJsonPath('session_complete', null);
});
