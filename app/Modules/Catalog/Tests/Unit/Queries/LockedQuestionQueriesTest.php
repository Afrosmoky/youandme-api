<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use App\Modules\Catalog\Queries\GetDailyQuestionPoolQuery;
use App\Modules\Catalog\Queries\IsQuestionLockedQuery;
use App\Modules\Catalog\Queries\ListLockedQuestionsQuery;

test('lists the locked part of the session deck in a stable order', function (): void {
    $cat = Category::factory()->create();
    Question::factory()->create(['category_id' => $cat->id]);
    $first = Question::factory()->locked()->create(['category_id' => $cat->id]);
    $second = Question::factory()->locked()->create(['category_id' => $cat->id]);

    $result = ListLockedQuestionsQuery::run();

    expect(collect($result)->pluck('ulid')->all())->toBe([$first->ulid, $second->ulid]);
});

test('the locked list ignores the daily deck', function (): void {
    Question::factory()->locked()->create(['type' => 'daily', 'category_id' => null]);

    expect(ListLockedQuestionsQuery::run())->toBe([]);
});

test('tells whether a question is locked', function (): void {
    $free = Question::factory()->create();
    $locked = Question::factory()->locked()->create();

    expect(IsQuestionLockedQuery::run($locked->id))->toBeTrue()
        ->and(IsQuestionLockedQuery::run($free->id))->toBeFalse();
});

test('the daily pool skips a locked daily question', function (): void {
    $free = Question::factory()->create(['type' => 'daily', 'category_id' => null]);
    Question::factory()->locked()->create(['type' => 'daily', 'category_id' => null]);

    expect(GetDailyQuestionPoolQuery::run())->toBe([$free->id]);
});
