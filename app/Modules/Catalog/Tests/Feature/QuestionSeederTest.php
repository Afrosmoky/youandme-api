<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;

test('seeder loads 100 questions from the deck', function (): void {
    $this->seed();

    expect(Question::count())->toBeGreaterThanOrEqual(100);
});

test('seeder assigns categories correctly', function (): void {
    $this->seed();

    $naPoznanie = Category::where('slug', 'na_poznanie')->firstOrFail();

    expect(Question::where('category_id', $naPoznanie->id)->count())
        ->toBeGreaterThanOrEqual(60);
});

test('seeder populates tags', function (): void {
    $this->seed();

    expect(Question::whereJsonLength('tags', '>', 0)->count())->toBeGreaterThan(0);
});
