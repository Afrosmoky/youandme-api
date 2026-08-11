<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 | GET /questions/deck — the local game's deck. Same pool rule as a session, dealt
 | in one call instead of card by card, because the phone plays it offline.
 */

test('the deck hands out the couple playable cards', function (): void {
    $questions = Question::factory()->count(3)->create();
    Sanctum::actingAs(createUserWithCouple());

    $response = $this->getJson('/api/v1/questions/deck')->assertOk();

    expect($response->json('questions'))->toHaveCount(3)
        ->and($response->json('questions.*.ulid'))->toEqualCanonicalizing($questions->pluck('ulid')->all());
});

test('a deck card carries the same shape as a session card', function (): void {
    $category = Category::factory()->create(['slug' => 'randka', 'name' => 'Randka']);
    Question::factory()->create([
        'category_id' => $category->id,
        'body' => 'O czym marzycie?',
        'tags' => ['bliskosc'],
    ]);
    Sanctum::actingAs(createUserWithCouple());

    $card = $this->getJson('/api/v1/questions/deck')->assertOk()->json('questions.0');

    // S3a: liked included, in the same place it sits on a session card. The
    // merged screen hearts cards dealt from here, so one shape, not two.
    expect(array_keys($card))->toBe(['ulid', 'body', 'type', 'category', 'tags', 'liked', 'is_locked', 'options'])
        ->and($card['liked'])->toBeFalse()
        ->and($card['body'])->toBe('O czym marzycie?')
        ->and($card['type'])->toBe('session')
        ->and($card['category'])->toBe(['slug' => 'randka', 'name' => 'Randka'])
        ->and($card['tags'])->toBe(['bliskosc'])
        ->and($card['is_locked'])->toBeFalse()
        // An open card says so with null, not by leaving the key out: the client
        // reads one field to decide how to answer, on every card.
        ->and($card['options'])->toBeNull();
});

test('a choice card carries its options envelope as stored', function (): void {
    Question::factory()->create([
        'body' => 'Które z poniższych wybierasz?',
        'options' => ['items' => ['Pierwsza', 'Druga', 'Trzecia'], 'multiple' => true],
    ]);
    Sanctum::actingAs(createUserWithCouple());

    $card = $this->getJson('/api/v1/questions/deck')->assertOk()->json('questions.0');

    // Served exactly as stored — no flattening into two fields, no renaming.
    expect($card['options'])->toBe([
        'items' => ['Pierwsza', 'Druga', 'Trzecia'],
        'multiple' => true,
    ]);
});

test('a session card is unchanged — the deck gaining liked did not touch /questions/next', function (): void {
    $question = Question::factory()->create();
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/sessions/start')->assertCreated();

    $card = $this->getJson('/api/v1/questions/next')->assertOk()->json('question');

    expect(array_keys($card))->toBe(['ulid', 'body', 'type', 'category', 'tags', 'liked', 'is_locked', 'options'])
        ->and($card['ulid'])->toBe($question->ulid);
});

test('a card the couple hearted is dealt with liked:true, the rest with false', function (): void {
    $liked = Question::factory()->create();
    $plain = Question::factory()->create();
    $user = createUserWithCouple();
    activeCoupleOf($user)->likedQuestions()->attach($liked->id, ['liked_at' => now()]);
    Sanctum::actingAs($user);

    $cards = collect($this->getJson('/api/v1/questions/deck')->assertOk()->json('questions'))
        ->keyBy('ulid');

    expect($cards[$liked->ulid]['liked'])->toBeTrue()
        ->and($cards[$plain->ulid]['liked'])->toBeFalse();
});

test('one couple hearts do not travel to another couple deck', function (): void {
    $question = Question::factory()->create();
    $fan = createUserWithCouple();
    activeCoupleOf($fan)->likedQuestions()->attach($question->id, ['liked_at' => now()]);

    Sanctum::actingAs(createUserWithCouple());

    $card = $this->getJson('/api/v1/questions/deck')->assertOk()->json('questions.0');

    // Same sense as on /questions/next: did THIS couple heart THIS card.
    expect($card['liked'])->toBeFalse();
});

test('the deck reads hearts once, not once per card', function (): void {
    $questions = Question::factory()->count(20)->create();
    $user = createUserWithCouple();
    activeCoupleOf($user)->likedQuestions()->attach($questions[0]->id, ['liked_at' => now()]);
    Sanctum::actingAs($user);

    DB::enableQueryLog();
    $this->getJson('/api/v1/questions/deck')->assertOk();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // The N+1 line the batch resolver already holds for questions and categories:
    // a deck of any size costs one lookup for the like state, not one per card.
    $likeQueries = array_filter(
        $queries,
        fn (array $query): bool => str_contains((string) $query['query'], 'couple_question_likes'),
    );

    expect($likeQueries)->toHaveCount(1);
});

test('cards the couple has already played stay out of the deck', function (): void {
    $questions = Question::factory()->count(3)->create();
    $user = createUserWithCouple();
    activeCoupleOf($user)->seenQuestions()->attach($questions[0]->id, ['seen_at' => now()]);
    Sanctum::actingAs($user);

    $ulids = $this->getJson('/api/v1/questions/deck')->assertOk()->json('questions.*.ulid');

    expect($ulids)->toHaveCount(2)
        ->and($ulids)->not->toContain($questions[0]->ulid);
});

test('the played set of one couple does not shrink another couple deck', function (): void {
    $questions = Question::factory()->count(2)->create();
    $busy = createUserWithCouple();
    activeCoupleOf($busy)->seenQuestions()->attach($questions[0]->id, ['seen_at' => now()]);

    Sanctum::actingAs(createUserWithCouple());

    expect($this->getJson('/api/v1/questions/deck')->assertOk()->json('questions'))->toHaveCount(2);
});

test('a locked card the couple has not unlocked never leaves the endpoint', function (): void {
    $free = Question::factory()->create();
    Question::factory()->locked()->create();
    Sanctum::actingAs(createUserWithCouple());

    $ulids = $this->getJson('/api/v1/questions/deck')->assertOk()->json('questions.*.ulid');

    // The security line (canon §5): sequencing happens on the phone, entitlement
    // does not — a card that is not dealt cannot be played.
    expect($ulids)->toBe([$free->ulid]);
});

test('a locked card the couple unlocked is dealt and flagged', function (): void {
    $locked = Question::factory()->locked()->create();
    $user = createUserWithCouple();
    activeCoupleOf($user)->unlockedQuestions()->attach($locked->id, [
        'unlocked_at' => now(),
        'source' => 'credits',
    ]);
    Sanctum::actingAs($user);

    $card = $this->getJson('/api/v1/questions/deck')->assertOk()->json('questions.0');

    expect($card['ulid'])->toBe($locked->ulid)
        ->and($card['is_locked'])->toBeTrue();
});

test('a category narrows the deck', function (): void {
    $date = Category::factory()->create(['slug' => 'randka']);
    $other = Category::factory()->create(['slug' => 'intymnosc']);
    $wanted = Question::factory()->create(['category_id' => $date->id]);
    Question::factory()->count(2)->create(['category_id' => $other->id]);
    Sanctum::actingAs(createUserWithCouple());

    $ulids = $this->getJson('/api/v1/questions/deck?category_slug=randka')->assertOk()->json('questions.*.ulid');

    expect($ulids)->toBe([$wanted->ulid]);
});

test('no category means mix — every category is in play', function (): void {
    $date = Category::factory()->create(['slug' => 'randka']);
    $other = Category::factory()->create(['slug' => 'intymnosc']);
    Question::factory()->create(['category_id' => $date->id]);
    Question::factory()->create(['category_id' => $other->id]);
    Sanctum::actingAs(createUserWithCouple());

    expect($this->getJson('/api/v1/questions/deck')->assertOk()->json('questions'))->toHaveCount(2);
});

test('an unknown category is a 422, not an empty deck', function (): void {
    Question::factory()->create();
    Sanctum::actingAs(createUserWithCouple());

    $this->getJson('/api/v1/questions/deck?category_slug=nie-ma-takiej')
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_slug');
});

test('daily cards are not part of the deck', function (): void {
    $session = Question::factory()->create();
    Question::factory()->count(2)->create(['type' => 'daily']);
    Sanctum::actingAs(createUserWithCouple());

    $ulids = $this->getJson('/api/v1/questions/deck')->assertOk()->json('questions.*.ulid');

    expect($ulids)->toBe([$session->ulid]);
});

test('an exhausted deck is an empty list, not an error', function (): void {
    $questions = Question::factory()->count(2)->create();
    $user = createUserWithCouple();
    activeCoupleOf($user)->seenQuestions()->attach(
        $questions->mapWithKeys(fn ($q): array => [$q->id => ['seen_at' => now()]])->all()
    );
    Sanctum::actingAs($user);

    // S4a: still an empty list and still a 200 — the reason rides alongside it.
    $this->getJson('/api/v1/questions/deck')
        ->assertOk()
        ->assertExactJson([
            'questions' => [],
            'exhaustion' => ['reason' => 'complete', 'locked_remaining' => 0],
        ]);
});

test('a couple with no cards at all gets an empty deck', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    $this->getJson('/api/v1/questions/deck')->assertOk()->assertExactJson([
        'questions' => [],
        'exhaustion' => ['reason' => 'complete', 'locked_remaining' => 0],
    ]);
});

/*
 | S4a — why the deck is empty. The client shows a different screen per reason,
 | and the one it used to show ("pick another category") was wrong for exactly the
 | couple that has played the most.
 */

test('a full deck carries no exhaustion key at all', function (): void {
    Question::factory()->create();
    Sanctum::actingAs(createUserWithCouple());

    $response = $this->getJson('/api/v1/questions/deck')->assertOk();

    // Additive: the contract only grows where there was nothing to say.
    expect($response->json('questions'))->toHaveCount(1)
        ->and($response->json())->not->toHaveKey('exhaustion');
});

test('an empty category with free cards elsewhere says other_categories', function (): void {
    $empty = Category::factory()->create(['slug' => 'randka']);
    $other = Category::factory()->create(['slug' => 'intymnosc']);
    $played = Question::factory()->create(['category_id' => $empty->id]);
    Question::factory()->count(2)->create(['category_id' => $other->id]);

    $user = createUserWithCouple();
    activeCoupleOf($user)->seenQuestions()->attach($played->id, ['seen_at' => now()]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/questions/deck?category_slug=randka')
        ->assertOk()
        ->assertJsonPath('questions', [])
        ->assertJsonPath('exhaustion.reason', 'other_categories');
});

test('other_categories wins over the unlock funnel — free cards first', function (): void {
    $empty = Category::factory()->create(['slug' => 'randka']);
    $other = Category::factory()->create(['slug' => 'intymnosc']);
    Question::factory()->create(['category_id' => $other->id]);
    Question::factory()->locked()->create(['category_id' => $empty->id]);

    Sanctum::actingAs(createUserWithCouple());

    // The locked card in the asked-for category is not playable, and there is a
    // free one elsewhere: sell nothing, send them to what they own.
    $this->getJson('/api/v1/questions/deck?category_slug=randka')
        ->assertOk()
        ->assertJsonPath('exhaustion.reason', 'other_categories')
        ->assertJsonPath('exhaustion.locked_remaining', 1);
});

test('a card with no category counts as playable elsewhere', function (): void {
    $asked = Category::factory()->create(['slug' => 'randka']);
    $played = Question::factory()->create(['category_id' => $asked->id]);
    Question::factory()->create(['category_id' => null]);

    $user = createUserWithCouple();
    activeCoupleOf($user)->seenQuestions()->attach($played->id, ['seen_at' => now()]);
    Sanctum::actingAs($user);

    // questions.category_id is nullable and `category_id <> ?` would drop this
    // row, sending the couple to the unlock funnel with a free card still unplayed.
    $this->getJson('/api/v1/questions/deck?category_slug=randka')
        ->assertOk()
        ->assertJsonPath('exhaustion.reason', 'other_categories');
});

test('everything playable seen, locked cards left says locked_available', function (): void {
    $free = Question::factory()->create();
    $bought = Question::factory()->locked()->create();
    Question::factory()->locked()->count(3)->create();

    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $couple->unlockedQuestions()->attach($bought->id, ['unlocked_at' => now(), 'source' => 'credits']);
    $couple->seenQuestions()->attach([
        $free->id => ['seen_at' => now()],
        $bought->id => ['seen_at' => now()],
    ]);
    Sanctum::actingAs($user);

    // Both the free card and the one they bought are played; three stay behind
    // the lock. This is the funnel, not "the deck is finished".
    $this->getJson('/api/v1/questions/deck')
        ->assertOk()
        ->assertJsonPath('questions', [])
        ->assertJsonPath('exhaustion.reason', 'locked_available')
        ->assertJsonPath('exhaustion.locked_remaining', 3);
});

test('a locked card the couple already unlocked is not counted as remaining', function (): void {
    $free = Question::factory()->create();
    $bought = Question::factory()->locked()->create();

    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $couple->unlockedQuestions()->attach($bought->id, ['unlocked_at' => now(), 'source' => 'credits']);
    $couple->seenQuestions()->attach([
        $free->id => ['seen_at' => now()],
        $bought->id => ['seen_at' => now()],
    ]);
    Sanctum::actingAs($user);

    // Nothing to sell them: the only locked card in the deck is already theirs.
    $this->getJson('/api/v1/questions/deck')
        ->assertOk()
        ->assertJsonPath('exhaustion.reason', 'complete')
        ->assertJsonPath('exhaustion.locked_remaining', 0);
});

test('the whole deck played says complete with nothing left to unlock', function (): void {
    $questions = Question::factory()->count(4)->create();

    $user = createUserWithCouple();
    activeCoupleOf($user)->seenQuestions()->attach(
        $questions->mapWithKeys(fn ($q): array => [$q->id => ['seen_at' => now()]])->all()
    );
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/questions/deck')
        ->assertOk()
        ->assertJsonPath('exhaustion.reason', 'complete')
        ->assertJsonPath('exhaustion.locked_remaining', 0);
});

test('an exhausted mix never says other_categories', function (): void {
    $date = Category::factory()->create(['slug' => 'randka']);
    $other = Category::factory()->create(['slug' => 'intymnosc']);
    $questions = collect([
        Question::factory()->create(['category_id' => $date->id]),
        Question::factory()->create(['category_id' => $other->id]),
    ]);

    $user = createUserWithCouple();
    activeCoupleOf($user)->seenQuestions()->attach(
        $questions->mapWithKeys(fn ($q): array => [$q->id => ['seen_at' => now()]])->all()
    );
    Sanctum::actingAs($user);

    // Mix already spans every category — "try another one" is unanswerable here,
    // and the endpoint does not even ask the question (see GetDeckExhaustionQuery).
    $this->getJson('/api/v1/questions/deck')
        ->assertOk()
        ->assertJsonPath('exhaustion.reason', 'complete');
});

test('daily cards are not counted into the exhaustion reason', function (): void {
    $session = Question::factory()->create();
    Question::factory()->count(2)->create(['type' => 'daily']);
    Question::factory()->locked()->create(['type' => 'daily']);

    $user = createUserWithCouple();
    activeCoupleOf($user)->seenQuestions()->attach($session->id, ['seen_at' => now()]);
    Sanctum::actingAs($user);

    // Same pool rule as the deal: the daily loop is disjoint (P4), so it neither
    // props up "there is more elsewhere" nor inflates the unlock funnel.
    $this->getJson('/api/v1/questions/deck')
        ->assertOk()
        ->assertJsonPath('exhaustion.reason', 'complete')
        ->assertJsonPath('exhaustion.locked_remaining', 0);
});

test('another couple progress does not change our exhaustion reason', function (): void {
    $free = Question::factory()->create();
    Question::factory()->locked()->create();

    $busy = createUserWithCouple();
    activeCoupleOf($busy)->seenQuestions()->attach($free->id, ['seen_at' => now()]);

    $user = createUserWithCouple();
    activeCoupleOf($user)->seenQuestions()->attach($free->id, ['seen_at' => now()]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/questions/deck')
        ->assertOk()
        ->assertJsonPath('exhaustion.reason', 'locked_available')
        ->assertJsonPath('exhaustion.locked_remaining', 1);
});

test('the exhaustion reason costs two aggregates, not a query per card', function (): void {
    $asked = Category::factory()->create(['slug' => 'randka']);
    Question::factory()->locked()->count(30)->create();
    $seen = Question::factory()->count(30)->create(['category_id' => $asked->id]);

    $user = createUserWithCouple();
    activeCoupleOf($user)->seenQuestions()->attach(
        $seen->mapWithKeys(fn ($q): array => [$q->id => ['seen_at' => now()]])->all()
    );
    Sanctum::actingAs($user);

    DB::enableQueryLog();
    $this->getJson('/api/v1/questions/deck?category_slug=randka&limit=100')->assertOk();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $counts = array_filter(
        $queries,
        fn (array $query): bool => str_contains((string) $query['query'], 'count(*)')
            && str_contains((string) $query['query'], '"questions"'),
    );

    // Two, whatever the deck size: one "elsewhere", one "behind the lock".
    expect($counts)->toHaveCount(2);
});

test('a deck that dealt cards asks no exhaustion question at all', function (): void {
    Question::factory()->count(3)->create();
    Sanctum::actingAs(createUserWithCouple());

    DB::enableQueryLog();
    $this->getJson('/api/v1/questions/deck')->assertOk();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $counts = array_filter(
        $queries,
        fn (array $query): bool => str_contains((string) $query['query'], 'count(*)')
            && str_contains((string) $query['query'], '"questions"'),
    );

    // The normal path pays nothing for S4a.
    expect($counts)->toBeEmpty();
});

test('the deck honours a requested limit', function (): void {
    Question::factory()->count(5)->create();
    Sanctum::actingAs(createUserWithCouple());

    expect($this->getJson('/api/v1/questions/deck?limit=2')->assertOk()->json('questions'))->toHaveCount(2);
});

test('a limit over the ceiling is clamped rather than refused', function (): void {
    Question::factory()->count(3)->create();
    Sanctum::actingAs(createUserWithCouple());

    // 101 is past the cap a report can carry; the client gets what it may have.
    expect($this->getJson('/api/v1/questions/deck?limit=500')->assertOk()->json('questions'))->toHaveCount(3);
});

test('a nonsensical limit still yields a playable deck', function (): void {
    Question::factory()->count(3)->create();
    Sanctum::actingAs(createUserWithCouple());

    expect($this->getJson('/api/v1/questions/deck?limit=0')->assertOk()->json('questions'))->toHaveCount(1);
});

test('a non-numeric limit is a 422', function (): void {
    Question::factory()->create();
    Sanctum::actingAs(createUserWithCouple());

    $this->getJson('/api/v1/questions/deck?limit=duzo')
        ->assertStatus(422)
        ->assertJsonValidationErrors('limit');
});

test('reading a deck does not mark anything played', function (): void {
    Question::factory()->count(3)->create();
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/questions/deck')->assertOk();

    // CQS: dealing cards is not playing them. The report says what was played,
    // and until it arrives the couple's progress is untouched.
    expect(activeCoupleOf($user)->seenQuestions()->count())->toBe(0);
});

test('the deck requires a token', function (): void {
    $this->getJson('/api/v1/questions/deck')->assertUnauthorized();
});
