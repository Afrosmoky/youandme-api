<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 | GET /questions/liked — the couple's hearted cards. Until now a like was
 | write-only: it landed in couple_question_likes and had nowhere to surface, so
 | from the user's side hearting a card did nothing (beta feedback B1).
 |
 | Reads like the deck (same card shape), paginates like /memories (cursor +
 | per_page), and serves only cards this couple may actually open.
 */

test('the liked list returns the couple hearted cards, newest like first', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $oldest = Question::factory()->create();
    $middle = Question::factory()->create();
    $newest = Question::factory()->create();

    $couple->likedQuestions()->attach($oldest->id, ['liked_at' => now()->subDays(3)]);
    $couple->likedQuestions()->attach($middle->id, ['liked_at' => now()->subDay()]);
    $couple->likedQuestions()->attach($newest->id, ['liked_at' => now()]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/questions/liked')->assertOk();

    expect($response->json('data.*.ulid'))->toBe([$newest->ulid, $middle->ulid, $oldest->ulid]);
});

test('a liked card carries the same shape as a deck card', function (): void {
    $category = Category::factory()->create(['slug' => 'randka', 'name' => 'Randka']);
    $question = Question::factory()->create([
        'category_id' => $category->id,
        'body' => 'O czym marzycie?',
        'tags' => ['bliskosc'],
    ]);
    $user = createUserWithCouple();
    activeCoupleOf($user)->likedQuestions()->attach($question->id, ['liked_at' => now()]);
    Sanctum::actingAs($user);

    $card = $this->getJson('/api/v1/questions/liked')->assertOk()->json('data.0');

    // Byte-for-byte the deck/session card: one builder, not a second shape to
    // keep in step (the client renders this list with the card it already has).
    expect(array_keys($card))->toBe(['ulid', 'body', 'type', 'category', 'tags', 'liked', 'is_locked', 'options'])
        ->and($card['body'])->toBe('O czym marzycie?')
        ->and($card['type'])->toBe('session')
        ->and($card['category'])->toBe(['slug' => 'randka', 'name' => 'Randka'])
        ->and($card['tags'])->toBe(['bliskosc'])
        ->and($card['is_locked'])->toBeFalse()
        ->and($card['options'])->toBeNull()
        // Constant here by construction — every card on this list is hearted —
        // but kept so the shape does not fork.
        ->and($card['liked'])->toBeTrue();
});

test('a couple that hearted nothing gets an empty list, not an error', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    $this->getJson('/api/v1/questions/liked')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonStructure(['data', 'meta' => ['next_cursor', 'prev_cursor', 'per_page']]);
});

test('one couple hearted cards are invisible to another couple', function (): void {
    $userA = createUserWithCouple();
    $userB = createUserWithCouple();
    $mine = Question::factory()->create();
    $theirs = Question::factory()->create();

    activeCoupleOf($userA)->likedQuestions()->attach($mine->id, ['liked_at' => now()]);
    activeCoupleOf($userB)->likedQuestions()->attach($theirs->id, ['liked_at' => now()]);

    Sanctum::actingAs($userA);

    // The couple comes from the token: B's hearts are not reachable from A's
    // session, whatever A sends.
    expect($this->getJson('/api/v1/questions/liked')->assertOk()->json('data.*.ulid'))
        ->toBe([$mine->ulid]);
});

test('a locked card the couple has not unlocked never reaches the list', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $free = Question::factory()->create();
    $locked = Question::factory()->locked()->create();

    // Written straight to the pivot on purpose: the endpoint that creates likes
    // now refuses this, but rows made before that door closed are still in the
    // production table. They must not become a way to read paid content.
    $couple->likedQuestions()->attach($free->id, ['liked_at' => now()]);
    $couple->likedQuestions()->attach($locked->id, ['liked_at' => now()]);

    Sanctum::actingAs($user);

    expect($this->getJson('/api/v1/questions/liked')->assertOk()->json('data.*.ulid'))
        ->toBe([$free->ulid]);
});

test('a locked card the couple unlocked stays on the list, flagged', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $locked = Question::factory()->locked()->create();

    $couple->unlockedQuestions()->attach($locked->id, ['unlocked_at' => now(), 'source' => 'credits']);
    $couple->likedQuestions()->attach($locked->id, ['liked_at' => now()]);

    Sanctum::actingAs($user);

    $card = $this->getJson('/api/v1/questions/liked')->assertOk()->json('data.0');

    expect($card['ulid'])->toBe($locked->ulid)
        ->and($card['is_locked'])->toBeTrue();
});

test('a hearted daily card belongs on the list too', function (): void {
    $user = createUserWithCouple();
    $daily = Question::factory()->create(['type' => 'daily', 'category_id' => null]);
    activeCoupleOf($user)->likedQuestions()->attach($daily->id, ['liked_at' => now()]);
    Sanctum::actingAs($user);

    // The daily deck is free (P7) and hearting a daily card has worked since P5,
    // so the closed-deck filter must not swallow it — the heart would vanish
    // again, which is the bug this endpoint exists to close.
    expect($this->getJson('/api/v1/questions/liked')->assertOk()->json('data.*.ulid'))
        ->toBe([$daily->ulid]);
});

test('the list paginates by cursor like the memories list', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $questions = Question::factory()->count(5)->create();

    foreach ($questions as $index => $question) {
        $couple->likedQuestions()->attach($question->id, ['liked_at' => now()->subMinutes(5 - $index)]);
    }

    Sanctum::actingAs($user);

    $first = $this->getJson('/api/v1/questions/liked?per_page=2')->assertOk();
    expect($first->json('data'))->toHaveCount(2)
        ->and($first->json('meta.per_page'))->toBe(2)
        ->and($first->json('meta.next_cursor'))->not->toBeNull();

    $second = $this->getJson('/api/v1/questions/liked?per_page=2&cursor='.$first->json('meta.next_cursor'))
        ->assertOk();

    // Pages do not overlap and walk the whole set.
    $seen = array_merge($first->json('data.*.ulid'), $second->json('data.*.ulid'));
    expect($seen)->toHaveCount(4)
        ->and(array_unique($seen))->toHaveCount(4);
});

test('cards hearted in the same instant still page without skipping', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $questions = Question::factory()->count(4)->create();
    $sameInstant = now();

    foreach ($questions as $question) {
        $couple->likedQuestions()->attach($question->id, ['liked_at' => $sameInstant]);
    }

    Sanctum::actingAs($user);

    $first = $this->getJson('/api/v1/questions/liked?per_page=2')->assertOk();
    $second = $this->getJson('/api/v1/questions/liked?per_page=2&cursor='.$first->json('meta.next_cursor'))
        ->assertOk();

    // question_id is the cursor tiebreaker, for the same reason id is on the
    // memories list: a shared timestamp must not drop rows between pages.
    $seen = array_merge($first->json('data.*.ulid'), $second->json('data.*.ulid'));
    expect(array_unique($seen))->toHaveCount(4);
});

test('per_page is clamped, not rejected', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);

    foreach (Question::factory()->count(3)->create() as $question) {
        $couple->likedQuestions()->attach($question->id, ['liked_at' => now()]);
    }

    Sanctum::actingAs($user);

    // Same treatment ?per_page gets on the memories list: a ceiling and a floor,
    // never a 422.
    expect($this->getJson('/api/v1/questions/liked?per_page=500')->assertOk()->json('meta.per_page'))->toBe(50)
        ->and($this->getJson('/api/v1/questions/liked?per_page=0')->assertOk()->json('meta.per_page'))->toBe(1);
});

test('the list resolves a page in a constant number of question lookups', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);

    foreach (Question::factory()->count(20)->create() as $question) {
        $couple->likedQuestions()->attach($question->id, ['liked_at' => now()]);
    }

    Sanctum::actingAs($user);

    DB::enableQueryLog();
    $this->getJson('/api/v1/questions/liked?per_page=20')->assertOk();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $questionQueries = array_filter(
        $queries,
        fn (array $query): bool => str_contains((string) $query['query'], 'from "questions"'),
    );

    // The N+1 line: the closed-deck check, the batch resolve and its categories —
    // three, whatever the page size. Never one per card.
    expect($questionQueries)->toHaveCount(3);
});

test('GET /questions/liked requires authentication', function (): void {
    $this->getJson('/api/v1/questions/liked')->assertUnauthorized();
});
