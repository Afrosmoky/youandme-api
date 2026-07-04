<?php

use App\Modules\Catalog\Data\QuestionData;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use App\Modules\Catalog\Queries\GetQuestionByIdQuery;

test('resolves an internal id to QuestionData with its category', function (): void {
    $cat = Category::factory()->create(['slug' => 'randka', 'name' => 'Randka']);
    $q = Question::factory()->create(['category_id' => $cat->id, 'body' => 'Ile masz lat?']);

    $data = GetQuestionByIdQuery::run($q->id);

    expect($data)->toBeInstanceOf(QuestionData::class);
    expect($data->ulid)->toBe($q->ulid);
    expect($data->body)->toBe('Ile masz lat?');
    expect($data->category?->slug)->toBe('randka');
});

test('returns null for a missing id', function (): void {
    expect(GetQuestionByIdQuery::run(999999))->toBeNull();
});
