<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Progress\Models\ProgressMilestone;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 | The one promise of the deck reset that could fail silently: the progress map
 | does not move. couple_question_seen feeds both the deal and the map; the reset
 | may renew the deal, never the count — not at the reset, and not when the
 | couple plays the same cards through again.
 */

test('the map and its milestones stay put through a reset and a replay', function (): void {
    ProgressMilestone::factory()->at(2)->create();
    ProgressMilestone::factory()->at(3)->create();
    $questions = Question::factory()->count(2)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/game/local/report', ['question_ulids' => $questions->pluck('ulid')->all()])
        ->assertOk();

    $mapBefore = $this->getJson('/api/v1/progress')->assertOk()->json();
    $milestonesBefore = DB::table('couple_milestone_unlocks')->where('couple_id', $coupleId)->get()->all();

    expect($mapBefore['total_played'])->toBe(2)
        ->and($milestonesBefore)->toHaveCount(1);

    $this->travel(1)->minutes();
    $this->postJson('/api/v1/questions/deck/reset')->assertNoContent();

    $this->getJson('/api/v1/progress')->assertOk()->assertExactJson($mapBefore);

    // The same two cards played through again in the new deck: the report says
    // nothing is new, the map says the same number, no threshold is crossed.
    $this->travel(1)->minutes();
    $this->postJson('/api/v1/game/local/report', ['question_ulids' => $questions->pluck('ulid')->all()])
        ->assertOk()
        ->assertExactJson(['played_total' => 2, 'newly_played' => 0]);

    $this->getJson('/api/v1/progress')->assertOk()->assertExactJson($mapBefore);
    expect(DB::table('couple_milestone_unlocks')->where('couple_id', $coupleId)->get()->all())
        ->toEqual($milestonesBefore);
});

test('a card new to the couple after a reset still moves the map', function (): void {
    ProgressMilestone::factory()->at(3)->create();
    $played = Question::factory()->count(2)->create();
    $fresh = Question::factory()->create();

    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/game/local/report', ['question_ulids' => $played->pluck('ulid')->all()])->assertOk();
    $this->travel(1)->minutes();
    $this->postJson('/api/v1/questions/deck/reset')->assertNoContent();
    $this->travel(1)->minutes();

    // Replays add nothing, a first play adds one — the map counts cards, not decks.
    $this->postJson('/api/v1/game/local/report', [
        'question_ulids' => [...$played->pluck('ulid')->all(), $fresh->ulid],
    ])
        ->assertOk()
        ->assertExactJson(['played_total' => 3, 'newly_played' => 1]);

    $this->getJson('/api/v1/progress')
        ->assertOk()
        ->assertJsonPath('total_played', 3)
        ->assertJsonPath('milestones.0.unlocked', true);
});
