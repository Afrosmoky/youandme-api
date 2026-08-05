<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Events\CardsPlayed;
use App\Modules\Game\Models\Couple;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

function playedCardCount(Couple $couple): int
{
    return DB::table('couple_question_seen')->where('couple_id', $couple->id)->count();
}

test('a report marks the reported cards as played', function (): void {
    $user = createUserWithCouple();
    $questions = Question::factory()->count(3)->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/game/local/report', [
        'question_ulids' => $questions->pluck('ulid')->all(),
    ])
        ->assertOk()
        ->assertExactJson(['played_total' => 3, 'newly_played' => 3]);

    expect(playedCardCount(activeCoupleOf($user)))->toBe(3);
});

test('a repeated report does not double count (idempotent set)', function (): void {
    $user = createUserWithCouple();
    $questions = Question::factory()->count(2)->create();
    $payload = ['question_ulids' => $questions->pluck('ulid')->all()];
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/game/local/report', $payload)->assertOk();

    // The retry a flaky network produces: same batch, nothing new.
    $this->postJson('/api/v1/game/local/report', $payload)
        ->assertOk()
        ->assertExactJson(['played_total' => 2, 'newly_played' => 0]);

    expect(playedCardCount(activeCoupleOf($user)))->toBe(2);
});

test('an overlapping report counts only the cards that are new', function (): void {
    $user = createUserWithCouple();
    $questions = Question::factory()->count(3)->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/game/local/report', [
        'question_ulids' => [$questions[0]->ulid, $questions[1]->ulid],
    ])->assertOk();

    $this->postJson('/api/v1/game/local/report', [
        'question_ulids' => [$questions[1]->ulid, $questions[2]->ulid],
    ])
        ->assertOk()
        ->assertExactJson(['played_total' => 3, 'newly_played' => 1]);
});

test('cards played through a session and through a report share one set', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $question = Question::factory()->create();
    Sanctum::actingAs($user);

    // Already played in a remote session (the save path marks it too).
    $couple->seenQuestions()->attach($question->id, ['seen_at' => now()]);

    $this->postJson('/api/v1/game/local/report', ['question_ulids' => [$question->ulid]])
        ->assertOk()
        ->assertExactJson(['played_total' => 1, 'newly_played' => 0]);
});

test('a locked card the couple has not unlocked is dropped from the report', function (): void {
    $user = createUserWithCouple();
    $free = Question::factory()->create();
    $locked = Question::factory()->locked()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/game/local/report', [
        'question_ulids' => [$free->ulid, $locked->ulid],
    ])
        ->assertOk()
        ->assertExactJson(['played_total' => 1, 'newly_played' => 1]);

    expect(activeCoupleOf($user)->seenQuestions->contains($locked))->toBeFalse();
});

test('a locked card the couple unlocked is accepted', function (): void {
    $user = createUserWithCouple();
    $locked = Question::factory()->locked()->create();
    activeCoupleOf($user)->unlockedQuestions()->attach($locked->id, [
        'unlocked_at' => now(),
        'source' => 'credits',
    ]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/game/local/report', ['question_ulids' => [$locked->ulid]])
        ->assertOk()
        ->assertExactJson(['played_total' => 1, 'newly_played' => 1]);
});

test('a daily question is dropped — the map counts the question game only', function (): void {
    $user = createUserWithCouple();
    $daily = Question::factory()->create(['type' => 'daily']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/game/local/report', ['question_ulids' => [$daily->ulid]])
        ->assertOk()
        ->assertExactJson(['played_total' => 0, 'newly_played' => 0]);

    expect(playedCardCount(activeCoupleOf($user)))->toBe(0);
});

test('the report lands on the token holder couple, never on anyone else', function (): void {
    $reporter = createUserWithCouple();
    $stranger = createUserWithCouple();
    $question = Question::factory()->create();
    Sanctum::actingAs($reporter);

    // couple_id is not an input at all — sending one changes nothing.
    $this->postJson('/api/v1/game/local/report', [
        'question_ulids' => [$question->ulid],
        'couple_id' => activeCoupleOf($stranger)->id,
    ])->assertOk();

    expect(playedCardCount(activeCoupleOf($reporter)))->toBe(1)
        ->and(playedCardCount(activeCoupleOf($stranger)))->toBe(0);
});

test('reported cards no longer come back in a new session', function (): void {
    $user = createUserWithCouple();
    $questions = Question::factory()->count(3)->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/game/local/report', [
        'question_ulids' => [$questions[0]->ulid, $questions[1]->ulid],
    ])->assertOk();

    // Anti-repeat is the other half of what this set means: a card played on the
    // phone is not dealt again in a remote session.
    $this->postJson('/api/v1/sessions/start')->assertCreated()
        ->assertJsonPath('session.remaining_count', 1);

    $this->getJson('/api/v1/questions/next')
        ->assertOk()
        ->assertJsonPath('question.ulid', $questions[2]->ulid);
});

test('a report emits CardsPlayed once for the couple', function (): void {
    Event::fake([CardsPlayed::class]);

    $user = createUserWithCouple();
    $question = Question::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/game/local/report', ['question_ulids' => [$question->ulid]])->assertOk();

    $coupleId = activeCoupleOf($user)->id;
    Event::assertDispatchedTimes(CardsPlayed::class, 1);
    Event::assertDispatched(CardsPlayed::class, fn (CardsPlayed $event): bool => $event->coupleId === $coupleId);
});

test('a report of nothing playable emits no event', function (): void {
    Event::fake([CardsPlayed::class]);

    $user = createUserWithCouple();
    $daily = Question::factory()->create(['type' => 'daily']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/game/local/report', ['question_ulids' => [$daily->ulid]])->assertOk();

    Event::assertNotDispatched(CardsPlayed::class);
});

test('the report rejects an empty list', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/game/local/report', ['question_ulids' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('question_ulids');
});

test('the report rejects an unknown ulid', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/game/local/report', ['question_ulids' => ['01JZZZZZZZZZZZZZZZZZZZZZZZ']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('question_ulids.0');
});

test('the report rejects duplicates inside one batch', function (): void {
    $question = Question::factory()->create();
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/game/local/report', [
        'question_ulids' => [$question->ulid, $question->ulid],
    ])->assertStatus(422);
});

test('the report rejects a batch over the cap', function (): void {
    $question = Question::factory()->create();
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/game/local/report', [
        'question_ulids' => array_fill(0, 101, $question->ulid),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('question_ulids');
});

test('the report requires authentication', function (): void {
    $question = Question::factory()->create();

    $this->postJson('/api/v1/game/local/report', ['question_ulids' => [$question->ulid]])
        ->assertStatus(401);
});
