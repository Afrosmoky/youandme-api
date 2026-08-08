<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
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

test('a deck card carries the same shape as a session card, minus liked', function (): void {
    $category = Category::factory()->create(['slug' => 'randka', 'name' => 'Randka']);
    Question::factory()->create([
        'category_id' => $category->id,
        'body' => 'O czym marzycie?',
        'tags' => ['bliskosc'],
    ]);
    Sanctum::actingAs(createUserWithCouple());

    $card = $this->getJson('/api/v1/questions/deck')->assertOk()->json('questions.0');

    expect(array_keys($card))->toBe(['ulid', 'body', 'type', 'category', 'tags', 'is_locked', 'options'])
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

test('a session card still carries liked — the deck did not change /questions/next', function (): void {
    $question = Question::factory()->create();
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/sessions/start')->assertCreated();

    $card = $this->getJson('/api/v1/questions/next')->assertOk()->json('question');

    expect(array_keys($card))->toBe(['ulid', 'body', 'type', 'category', 'tags', 'liked', 'is_locked', 'options'])
        ->and($card['ulid'])->toBe($question->ulid);
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

    $this->getJson('/api/v1/questions/deck')
        ->assertOk()
        ->assertExactJson(['questions' => []]);
});

test('a couple with no cards at all gets an empty deck', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    $this->getJson('/api/v1/questions/deck')->assertOk()->assertExactJson(['questions' => []]);
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
