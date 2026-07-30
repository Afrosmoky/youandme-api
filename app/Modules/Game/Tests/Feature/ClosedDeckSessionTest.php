<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Actions\UnlockQuestionForCoupleAction;
use App\Modules\Game\Support\UnlockSource;
use Laravel\Sanctum\Sanctum;

/*
 | The closed deck seen from the game loop: a session may only draw free cards
 | plus the locked ones this couple bought. The filter lives in the pool build
 | (POST /sessions/start), because the pool is frozen into state.remaining_ids.
 */

test('a session pool skips locked cards', function (): void {
    $category = Category::factory()->create(['slug' => 'randka']);
    $free = Question::factory()->create(['category_id' => $category->id]);
    Question::factory()->locked()->count(3)->create(['category_id' => $category->id]);

    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/sessions/start')
        ->assertCreated()
        ->assertJsonPath('session.remaining_count', 1);

    $this->getJson('/api/v1/questions/next')
        ->assertOk()
        ->assertJsonPath('question.ulid', $free->ulid);
});

test('an unlocked card joins the pool of the next session', function (): void {
    $category = Category::factory()->create(['slug' => 'randka']);
    Question::factory()->create(['category_id' => $category->id]);
    $locked = Question::factory()->locked()->create(['category_id' => $category->id]);

    $user = createUserWithCouple();
    UnlockQuestionForCoupleAction::run(activeCoupleOf($user)->id, $locked->id, UnlockSource::Credits);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/sessions/start')
        ->assertCreated()
        ->assertJsonPath('session.remaining_count', 2);
});

test('a couple with nothing unlocked and a fully locked deck cannot start a session', function (): void {
    $category = Category::factory()->create(['slug' => 'randka']);
    Question::factory()->locked()->count(2)->create(['category_id' => $category->id]);

    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/sessions/start')->assertUnprocessable();
});

test('one couple unlocking a card does not widen another couple pool', function (): void {
    $category = Category::factory()->create(['slug' => 'randka']);
    Question::factory()->create(['category_id' => $category->id]);
    $locked = Question::factory()->locked()->create(['category_id' => $category->id]);

    $owner = createUserWithCouple();
    UnlockQuestionForCoupleAction::run(activeCoupleOf($owner)->id, $locked->id, UnlockSource::Credits);

    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/sessions/start')
        ->assertCreated()
        ->assertJsonPath('session.remaining_count', 1);
});
