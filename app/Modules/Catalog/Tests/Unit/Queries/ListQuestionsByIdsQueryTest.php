<?php

use App\Modules\Catalog\Data\QuestionData;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use App\Modules\Catalog\Queries\ListQuestionsByIdsQuery;
use Illuminate\Support\Facades\DB;

test('resolves a batch of ids to QuestionData with categories', function (): void {
    $category = Category::factory()->create(['slug' => 'randka', 'name' => 'Randka']);
    $questions = Question::factory()->count(2)->create(['category_id' => $category->id]);

    $data = ListQuestionsByIdsQuery::run($questions->pluck('id')->all());

    expect($data)->toHaveCount(2)
        ->and($data[0])->toBeInstanceOf(QuestionData::class)
        ->and($data[0]->category?->slug)->toBe('randka');
});

test('keeps the order it was given', function (): void {
    $questions = Question::factory()->count(3)->create();
    $wanted = [$questions[2]->id, $questions[0]->id, $questions[1]->id];

    $data = ListQuestionsByIdsQuery::run($wanted);

    expect(array_map(fn (QuestionData $q): string => $q->ulid, $data))
        ->toBe([$questions[2]->ulid, $questions[0]->ulid, $questions[1]->ulid]);
});

test('returns an empty list for an empty input', function (): void {
    Question::factory()->create();

    expect(ListQuestionsByIdsQuery::run([]))->toBe([]);
});

test('drops ids that no longer resolve', function (): void {
    $question = Question::factory()->create();

    expect(ListQuestionsByIdsQuery::run([$question->id, 999999]))->toHaveCount(1);
});

test('costs one query for the questions and one for their categories', function (): void {
    $category = Category::factory()->create();
    $questions = Question::factory()->count(20)->create(['category_id' => $category->id]);

    DB::enableQueryLog();
    ListQuestionsByIdsQuery::run($questions->pluck('id')->all());
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // The whole point of the batch resolver: a deck of any size stays flat.
    expect($queries)->toHaveCount(2);
});
