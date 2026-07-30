<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use App\Modules\Catalog\Queries\GetSessionQuestionPoolQuery;

// QuestionFactory defaults to type=session, locale=pl (session-eligible).

test('returns ids of questions not in the seen set', function (): void {
    $cat = Category::factory()->create();
    $q1 = Question::factory()->create(['category_id' => $cat->id]);
    $q2 = Question::factory()->create(['category_id' => $cat->id]);
    $q3 = Question::factory()->create(['category_id' => $cat->id]);

    $result = GetSessionQuestionPoolQuery::run([$q1->id, $q2->id]);

    expect($result)->toBe([$q3->id]);
});

test('an empty seen set returns every eligible question id', function (): void {
    $cat = Category::factory()->create();
    Question::factory()->count(3)->create(['category_id' => $cat->id]);

    expect(GetSessionQuestionPoolQuery::run([]))->toHaveCount(3);
});

test('excludes questions that are not session/pl eligible', function (): void {
    $cat = Category::factory()->create();
    $session = Question::factory()->create(['category_id' => $cat->id]);
    Question::factory()->create(['category_id' => $cat->id, 'type' => 'daily']);
    Question::factory()->create(['category_id' => $cat->id, 'locale' => 'en']);

    $result = GetSessionQuestionPoolQuery::run([]);

    expect($result)->toBe([$session->id]);
});

test('respects the category slug filter', function (): void {
    $randka = Category::factory()->create(['slug' => 'randka']);
    $intymnosc = Category::factory()->create(['slug' => 'intymnosc']);
    $r1 = Question::factory()->create(['category_id' => $randka->id]);
    $r2 = Question::factory()->create(['category_id' => $randka->id]);
    Question::factory()->create(['category_id' => $intymnosc->id]);

    $result = GetSessionQuestionPoolQuery::run([], [], 'randka');

    expect($result)->toHaveCount(2);
    expect(collect($result)->sort()->values()->all())
        ->toBe(collect([$r1->id, $r2->id])->sort()->values()->all());
});

test('respects the limit', function (): void {
    $cat = Category::factory()->create();
    Question::factory()->count(5)->create(['category_id' => $cat->id]);

    expect(GetSessionQuestionPoolQuery::run([], [], null, 2))->toHaveCount(2);
});

test('excludes locked questions the couple has not unlocked', function (): void {
    $cat = Category::factory()->create();
    $free = Question::factory()->create(['category_id' => $cat->id]);
    Question::factory()->locked()->create(['category_id' => $cat->id]);

    expect(GetSessionQuestionPoolQuery::run([], []))->toBe([$free->id]);
});

test('includes a locked question once its id is in the unlocked list', function (): void {
    $cat = Category::factory()->create();
    $free = Question::factory()->create(['category_id' => $cat->id]);
    $locked = Question::factory()->locked()->create(['category_id' => $cat->id]);

    $result = GetSessionQuestionPoolQuery::run([], [$locked->id]);

    expect(collect($result)->sort()->values()->all())
        ->toBe(collect([$free->id, $locked->id])->sort()->values()->all());
});

test('an unlocked card that was already seen stays out of the pool', function (): void {
    $cat = Category::factory()->create();
    $locked = Question::factory()->locked()->create(['category_id' => $cat->id]);

    expect(GetSessionQuestionPoolQuery::run([$locked->id], [$locked->id]))->toBe([]);
});
