<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Queries\ListLikedQuestionPageForCoupleQuery;

test('returns the couple hearted ids, newest like first', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    $older = Question::factory()->create();
    $newer = Question::factory()->create();
    $couple->likedQuestions()->attach($older->id, ['liked_at' => now()->subDay()]);
    $couple->likedQuestions()->attach($newer->id, ['liked_at' => now()]);

    $page = ListLikedQuestionPageForCoupleQuery::run($couple, 20, null);

    expect($page->questionIds)->toBe([$newer->id, $older->id])
        ->and($page->perPage)->toBe(20)
        ->and($page->nextCursor)->toBeNull()
        ->and($page->prevCursor)->toBeNull();
});

test('drops a locked card the couple has not unlocked', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    $free = Question::factory()->create();
    $locked = Question::factory()->locked()->create();
    $unlocked = Question::factory()->locked()->create();
    $couple->unlockedQuestions()->attach($unlocked->id, ['unlocked_at' => now(), 'source' => 'credits']);
    $couple->likedQuestions()->attach([
        $free->id => ['liked_at' => now()],
        $locked->id => ['liked_at' => now()],
        $unlocked->id => ['liked_at' => now()],
    ]);

    $page = ListLikedQuestionPageForCoupleQuery::run($couple, 20, null);

    // Entitlement arithmetic stays in Game: Catalog says which cards are locked,
    // Game says which of those this couple owns.
    expect($page->questionIds)->toEqualCanonicalizing([$free->id, $unlocked->id]);
});

test('hands back a cursor when there is another page', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    $questions = Question::factory()->count(3)->create();

    foreach ($questions as $index => $question) {
        $couple->likedQuestions()->attach($question->id, ['liked_at' => now()->subMinutes(3 - $index)]);
    }

    $first = ListLikedQuestionPageForCoupleQuery::run($couple, 2, null);
    expect($first->questionIds)->toHaveCount(2)
        ->and($first->nextCursor)->not->toBeNull();

    $second = ListLikedQuestionPageForCoupleQuery::run($couple, 2, $first->nextCursor);

    expect($second->questionIds)->toHaveCount(1)
        ->and($second->questionIds)->not->toContain($first->questionIds[0]);
});

test('a couple with no hearts gets an empty page, not a query error', function (): void {
    $page = ListLikedQuestionPageForCoupleQuery::run(activeCoupleOf(createUserWithCouple()), 20, null);

    expect($page->questionIds)->toBe([])
        ->and($page->nextCursor)->toBeNull();
});
