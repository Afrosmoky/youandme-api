<?php

use App\Modules\Catalog\Database\Seeders\CategorySeeder;
use App\Modules\Catalog\Database\Seeders\QuestionSeeder;
use App\Modules\Catalog\Models\Question;

/*
 | The seeders refuse a malformed content file instead of doing their best with
 | it. Every one of these used to be silent: a missing key meant a row keyed on
 | its own text, a duplicate meant one card overwriting another, an orphan option
 | meant a card quietly losing its answers.
 */

/**
 * Run the question seeder against a temporary version of the deck file.
 *
 * @param  list<array<string, mixed>>  $entries
 */
function seedSessionDeckFrom(array $entries): void
{
    $path = base_path('app/Modules/Catalog/Database/Seeders/data/questions_with_categories_pl.json');
    $original = (string) file_get_contents($path);

    file_put_contents($path, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    try {
        (new CategorySeeder)->run();
        (new QuestionSeeder)->run();
    } finally {
        file_put_contents($path, $original);
    }
}

test('an entry without a seed_key stops the seeder', function (): void {
    seedSessionDeckFrom([
        ['body' => 'Pytanie bez klucza.', 'category_slug' => 'randka', 'tags' => [], 'is_locked' => false],
    ]);
})->throws(RuntimeException::class, 'has no seed_key');

test('two entries sharing a seed_key stop the seeder', function (): void {
    seedSessionDeckFrom([
        ['seed_key' => 'q001', 'body' => 'Pierwsze.', 'category_slug' => 'randka', 'tags' => [], 'is_locked' => false],
        ['seed_key' => 'q001', 'body' => 'Drugie.', 'category_slug' => 'randka', 'tags' => [], 'is_locked' => false],
    ]);
})->throws(RuntimeException::class, 'Duplicate seed_key');

test('an unknown category slug still stops the seeder', function (): void {
    seedSessionDeckFrom([
        ['seed_key' => 'q001', 'body' => 'Pytanie.', 'category_slug' => 'nie_ma_takiej', 'tags' => [], 'is_locked' => false],
    ]);
})->throws(RuntimeException::class, 'Unknown category slug');

test('an options entry pointing at no card stops the seeder', function (): void {
    // The deck shrinks to one card; the fourteen options entries now point at
    // keys nothing carries.
    seedSessionDeckFrom([
        ['seed_key' => 'q999', 'body' => 'Jedyne pytanie.', 'category_slug' => 'randka', 'tags' => [], 'is_locked' => false],
    ]);
})->throws(RuntimeException::class, 'match no question seed_key');

test('a card with no is_locked stops the seeder', function (): void {
    $path = base_path('app/Modules/Catalog/Database/Seeders/data/questions_with_categories_pl.json');
    /** @var list<array<string, mixed>> $entries */
    $entries = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    // Whether a card is paid for is a decision somebody makes. Absent is not
    // "free" — absent is a file nobody finished.
    foreach ($entries as $index => $entry) {
        if ($entry['seed_key'] === 'q100') {
            unset($entries[$index]['is_locked']);
        }
    }

    seedSessionDeckFrom(array_values($entries));
})->throws(RuntimeException::class, 'has no is_locked');
