<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Models\Couple;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 | POST /questions/deck/reset — "Zacznij od nowa". The next deal is the full deck
 | again; nothing the couple played, liked or bought is deleted. The progress map
 | side of the same promise is pinned in tests/Feature/Api/V1/DeckResetProgressTest.
 */

/** Mark cards as seen a while ago — before any reset the test is about to make. */
function seenEarlier(Couple $couple, iterable $questions): void
{
    foreach ($questions as $question) {
        $couple->seenQuestions()->attach($question->id, ['seen_at' => now()->subHour()]);
    }
}

test('a reset answers 204 and stamps the couple', function (): void {
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/deck/reset')->assertNoContent();

    expect(activeCoupleOf($user)->deck_reset_at)->not->toBeNull();
});

test('after a reset the deck is whole again', function (): void {
    $questions = Question::factory()->count(4)->create();
    $user = createUserWithCouple();
    seenEarlier(activeCoupleOf($user), $questions);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/questions/deck')->assertOk()->assertJsonPath('questions', []);

    $this->postJson('/api/v1/questions/deck/reset')->assertNoContent();

    $response = $this->getJson('/api/v1/questions/deck')->assertOk()->assertJsonMissingPath('exhaustion');
    expect($response->json('questions.*.ulid'))->toEqualCanonicalizing($questions->pluck('ulid')->all());
});

test('the reset deletes no played card', function (): void {
    $questions = Question::factory()->count(3)->create();
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    seenEarlier($couple, $questions);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/deck/reset')->assertNoContent();

    expect(DB::table('couple_question_seen')->where('couple_id', $couple->id)->count())->toBe(3);
});

test('a card replayed after the reset stays out of the next deal', function (): void {
    [$replayed, $other] = Question::factory()->count(2)->create();
    $user = createUserWithCouple();
    seenEarlier(activeCoupleOf($user), [$replayed, $other]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/deck/reset')->assertNoContent();
    $this->travel(1)->minutes();

    // The card already sits in the set, so this is an update of seen_at, not an
    // insert — and it is that refreshed seen_at that keeps it out of the deal.
    $this->postJson('/api/v1/game/local/report', ['question_ulids' => [$replayed->ulid]])
        ->assertOk()
        ->assertJsonPath('newly_played', 0);

    $this->getJson('/api/v1/questions/deck')
        ->assertOk()
        ->assertJsonPath('questions.*.ulid', [$other->ulid]);
});

test('a deck played through again after a reset reads as complete', function (): void {
    $questions = Question::factory()->count(2)->create();
    $user = createUserWithCouple();
    seenEarlier(activeCoupleOf($user), $questions);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/deck/reset')->assertNoContent();
    $this->travel(1)->minutes();
    $this->postJson('/api/v1/game/local/report', ['question_ulids' => $questions->pluck('ulid')->all()])
        ->assertOk();

    $this->getJson('/api/v1/questions/deck')
        ->assertOk()
        ->assertJsonPath('questions', [])
        ->assertJsonPath('exhaustion.reason', 'complete');
});

test('exhaustion after a reset agrees with the deal', function (): void {
    $date = Category::factory()->create(['slug' => 'randka']);
    $other = Category::factory()->create(['slug' => 'intymnosc']);
    $replayed = Question::factory()->create(['category_id' => $date->id]);
    $playedBeforeOnly = Question::factory()->create(['category_id' => $other->id]);
    Question::factory()->locked()->create();

    $user = createUserWithCouple();
    seenEarlier(activeCoupleOf($user), [$replayed, $playedBeforeOnly]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/deck/reset')->assertNoContent();
    $this->travel(1)->minutes();
    $this->postJson('/api/v1/game/local/report', ['question_ulids' => [$replayed->ulid]])->assertOk();

    // "randka" is exhausted in the new deck, but the card played only before the
    // reset is back in play elsewhere — so the reason must be other_categories,
    // never "complete", and the deal elsewhere must actually hand it out.
    $this->getJson('/api/v1/questions/deck?category_slug=randka')
        ->assertOk()
        ->assertJsonPath('questions', [])
        ->assertJsonPath('exhaustion.reason', 'other_categories')
        ->assertJsonPath('exhaustion.locked_remaining', 1);

    $this->getJson('/api/v1/questions/deck?category_slug=intymnosc')
        ->assertOk()
        ->assertJsonPath('questions.*.ulid', [$playedBeforeOnly->ulid]);
});

test('likes and unlocked cards survive a reset', function (): void {
    $liked = Question::factory()->create();
    $bought = Question::factory()->locked()->create();
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $couple->likedQuestions()->attach($liked->id, ['liked_at' => now()]);
    $couple->unlockedQuestions()->attach($bought->id, ['unlocked_at' => now(), 'source' => 'credits']);
    seenEarlier($couple, [$liked, $bought]);
    Sanctum::actingAs($user);

    $deckBefore = $this->getJson('/api/v1/deck')->assertOk()->json();

    $this->postJson('/api/v1/questions/deck/reset')->assertNoContent();

    expect($couple->likedQuestions()->pluck('questions.id')->all())->toBe([$liked->id])
        ->and($couple->unlockedQuestions()->pluck('questions.id')->all())->toBe([$bought->id]);
    $this->getJson('/api/v1/deck')->assertOk()->assertExactJson($deckBefore);

    // Both come back in the deal — the bought card still playable, the liked one
    // still hearted.
    $cards = collect($this->getJson('/api/v1/questions/deck')->assertOk()->json('questions'))->keyBy('ulid');
    expect($cards->keys()->all())->toEqualCanonicalizing([$liked->ulid, $bought->ulid])
        ->and($cards[$liked->ulid]['liked'])->toBeTrue();
});

test('a session started after a reset draws from the whole deck', function (): void {
    $questions = Question::factory()->count(3)->create();
    $user = createUserWithCouple();
    seenEarlier(activeCoupleOf($user), $questions);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/sessions/start')->assertUnprocessable();

    $this->postJson('/api/v1/questions/deck/reset')->assertNoContent();

    $this->postJson('/api/v1/sessions/start')->assertCreated();
    expect(activeCoupleOf($user)->gameSessions()->active()->firstOrFail()->state['remaining_ids'])
        ->toEqualCanonicalizing($questions->pluck('id')->all());
});

test('a reset touches only the calling couple', function (): void {
    $question = Question::factory()->create();
    $other = createUserWithCouple();
    seenEarlier(activeCoupleOf($other), [$question]);
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/questions/deck/reset')->assertNoContent();

    expect(activeCoupleOf($other)->deck_reset_at)->toBeNull();
});

test('a reset requires a token', function (): void {
    $this->postJson('/api/v1/questions/deck/reset')->assertUnauthorized();
});

test('the reset is throttled', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/questions/deck/reset')->assertNoContent();
    }

    $this->postJson('/api/v1/questions/deck/reset')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});
