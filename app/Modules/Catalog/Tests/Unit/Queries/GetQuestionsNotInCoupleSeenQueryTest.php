<?php

use App\Modules\Catalog\Data\QuestionData;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use App\Modules\Catalog\Queries\GetQuestionsNotInCoupleSeenQuery;

test('returns questions whose id is not in the seen set', function (): void {
    $cat = Category::factory()->create();
    $q1 = Question::factory()->create(['category_id' => $cat->id]);
    $q2 = Question::factory()->create(['category_id' => $cat->id]);
    $q3 = Question::factory()->create(['category_id' => $cat->id]);

    $result = GetQuestionsNotInCoupleSeenQuery::run([$q1->id, $q2->id]);

    expect($result)->toHaveCount(1);
    expect($result[0])->toBeInstanceOf(QuestionData::class);
    expect($result[0]->ulid)->toBe($q3->ulid);
});

test('an empty seen set returns every question', function (): void {
    $cat = Category::factory()->create();
    Question::factory()->count(3)->create(['category_id' => $cat->id]);

    expect(GetQuestionsNotInCoupleSeenQuery::run([]))->toHaveCount(3);
});

test('respects the category slug filter', function (): void {
    $randka = Category::factory()->create(['slug' => 'randka']);
    $intymnosc = Category::factory()->create(['slug' => 'intymnosc']);
    Question::factory()->count(2)->create(['category_id' => $randka->id]);
    Question::factory()->create(['category_id' => $intymnosc->id]);

    $result = GetQuestionsNotInCoupleSeenQuery::run([], 'randka');

    expect($result)->toHaveCount(2);
    expect(collect($result)->every(fn (QuestionData $q) => $q->category?->slug === 'randka'))->toBeTrue();
});

test('respects the limit', function (): void {
    $cat = Category::factory()->create();
    Question::factory()->count(5)->create(['category_id' => $cat->id]);

    expect(GetQuestionsNotInCoupleSeenQuery::run([], null, 2))->toHaveCount(2);
});

test('does not touch the couple_question_seen table (input is ids, not a couple)', function (): void {
    // Sanity: the query only needs a plain int[] — no Game concept involved.
    $cat = Category::factory()->create();
    $q = Question::factory()->create(['category_id' => $cat->id]);

    $result = GetQuestionsNotInCoupleSeenQuery::run([$q->id + 999]);

    expect($result)->toHaveCount(1);
    expect($result[0]->ulid)->toBe($q->ulid);
});
