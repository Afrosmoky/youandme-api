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

test('seeder locks the last 40 entries of the deck and leaves the rest free', function (): void {
    $this->seed();

    $sessionDeck = Question::where('type', 'session')->where('locale', 'pl');

    expect((clone $sessionDeck)->where('is_locked', true)->count())->toBe(40)
        ->and((clone $sessionDeck)->where('is_locked', false)->count())->toBe(60);
});

test('re-seeding does not reshuffle which cards are locked', function (): void {
    $this->seed();
    $lockedUlids = Question::where('is_locked', true)->orderBy('id')->pluck('ulid')->all();

    $this->seed();

    expect(Question::where('is_locked', true)->orderBy('id')->pluck('ulid')->all())
        ->toBe($lockedUlids);
});

test('the daily deck stays free', function (): void {
    $this->seed();

    expect(Question::where('type', 'daily')->where('is_locked', true)->count())->toBe(0);
});
