<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Rewards\Actions\GrantCreditsAction;
use Laravel\Sanctum\Sanctum;

/*
 | The proof that paying for a card does anything. Pool tests elsewhere insert the
 | entitlement row directly (ClosedDeckSessionTest, QuestionDeckTest); these go
 | through the endpoints a couple actually pays at — POST /questions/{ulid}/unlock
 | and POST /questions/unlock — and then look at what gets dealt.
 |
 | Not observable in the app: the local deal draws 40 at random from the playable
 | pool (a session 20), and with 60 free cards a bought card shows up only by
 | chance. So the deal is made to cover the whole pool — a pool smaller than the
 | deal, or ?limit=100 at production scale — and membership is then a fact, not
 | luck.
 */

/**
 * @return list<string>
 */
function dealtUlids(object $test, int $limit = 100): array
{
    return $test->getJson("/api/v1/questions/deck?limit={$limit}")->assertOk()->json('questions.*.ulid');
}

test('a card bought with the single unlock is dealt, and only that one', function (): void {
    $free = Question::factory()->count(3)->create();
    [$bought, $stillLocked] = Question::factory()->locked()->count(2)->create();
    $user = createUserWithCouple();
    GrantCreditsAction::run(activeCoupleOf($user)->id, 1);
    Sanctum::actingAs($user);

    expect(dealtUlids($this))->toEqualCanonicalizing($free->pluck('ulid')->all());

    $this->postJson("/api/v1/questions/{$bought->ulid}/unlock")->assertOk();

    expect(dealtUlids($this))
        ->toEqualCanonicalizing([...$free->pluck('ulid')->all(), $bought->ulid])
        ->not->toContain($stillLocked->ulid);
});

test('cards bought with the bulk unlock are dealt', function (): void {
    $free = Question::factory()->count(3)->create();
    $locked = Question::factory()->locked()->count(4)->create();
    $bought = $locked->take(3);
    $user = createUserWithCouple();
    GrantCreditsAction::run(activeCoupleOf($user)->id, 3);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => $bought->pluck('ulid')->all()])
        ->assertOk()
        ->assertJsonPath('charged', 3);

    expect(dealtUlids($this))
        ->toEqualCanonicalizing([...$free->pluck('ulid')->all(), ...$bought->pluck('ulid')->all()])
        ->not->toContain($locked[3]->ulid);
});

test('a bought card enters the pool of the next session', function (): void {
    Question::factory()->count(2)->create();
    $locked = Question::factory()->locked()->create();
    $user = createUserWithCouple();
    GrantCreditsAction::run(activeCoupleOf($user)->id, 1);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => [$locked->ulid]])->assertOk();

    $this->postJson('/api/v1/sessions/start')
        ->assertCreated()
        ->assertJsonPath('session.remaining_count', 3);
});

test('at production scale every bought card is in the playable pool', function (): void {
    // The seeded shape: 60 free session cards, 40 in the closed deck.
    $free = Question::factory()->count(60)->create();
    $locked = Question::factory()->locked()->count(40)->create();
    $bought = $locked->random(10);
    $user = createUserWithCouple();
    GrantCreditsAction::run(activeCoupleOf($user)->id, 10);
    Sanctum::actingAs($user);

    // The default deal is 40 either way — which is why the app cannot show this.
    expect($this->getJson('/api/v1/questions/deck')->json('questions'))->toHaveCount(40);
    expect(dealtUlids($this))->toHaveCount(60);

    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => $bought->pluck('ulid')->values()->all()])
        ->assertOk()
        ->assertJsonPath('charged', 10);

    $dealt = dealtUlids($this);

    expect($dealt)->toHaveCount(70)
        ->toEqualCanonicalizing([...$free->pluck('ulid')->all(), ...$bought->pluck('ulid')->all()])
        ->and($this->getJson('/api/v1/questions/deck')->json('questions'))->toHaveCount(40);
});
