<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Queries\ListLikedQuestionUlidsForCoupleQuery;
use Illuminate\Support\Facades\DB;

test('returns only the hearted subset of the ulids given', function (): void {
    $liked = Question::factory()->create();
    $plain = Question::factory()->create();
    $likedElsewhere = Question::factory()->create();
    $couple = activeCoupleOf(createUserWithCouple());
    $couple->likedQuestions()->attach([
        $liked->id => ['liked_at' => now()],
        $likedElsewhere->id => ['liked_at' => now()],
    ]);

    $result = ListLikedQuestionUlidsForCoupleQuery::run($couple, [$liked->ulid, $plain->ulid]);

    // Hearts outside the deck asked about stay out of the answer.
    expect($result)->toBe([$liked->ulid]);
});

test('another couple hearts are invisible', function (): void {
    $question = Question::factory()->create();
    activeCoupleOf(createUserWithCouple())->likedQuestions()->attach($question->id, ['liked_at' => now()]);

    $couple = activeCoupleOf(createUserWithCouple());

    expect(ListLikedQuestionUlidsForCoupleQuery::run($couple, [$question->ulid]))->toBe([]);
});

test('an empty deck costs no query at all', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());

    DB::enableQueryLog();
    $result = ListLikedQuestionUlidsForCoupleQuery::run($couple, []);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($result)->toBe([])
        ->and($queries)->toBeEmpty();
});

test('costs one query whatever the deck size', function (): void {
    $questions = Question::factory()->count(20)->create();
    $couple = activeCoupleOf(createUserWithCouple());
    $couple->likedQuestions()->attach($questions[0]->id, ['liked_at' => now()]);

    DB::enableQueryLog();
    ListLikedQuestionUlidsForCoupleQuery::run($couple, $questions->pluck('ulid')->all());
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toHaveCount(1);
});
