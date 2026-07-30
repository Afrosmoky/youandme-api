<?php

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use Illuminate\Database\Seeder;
use RuntimeException;

class QuestionSeeder extends Seeder
{
    /**
     * Size of the closed part of the deck (P7): the LAST 40 entries of the seed
     * file are locked, the rest is free. A TEMPORARY, position-based split — the
     * real 60/40 choice is Wiktoria's content decision and will arrive as a flag
     * in the JSON. Deriving it from the position keeps
     * questions_with_categories_pl.json untouched until then, and keeps the seed
     * deterministic (re-seeding never reshuffles which cards are locked).
     */
    private const LOCKED_TAIL = 40;

    /**
     * Re-seed the official deck from Wiktoria's parsed JSON: 100 questions, each
     * with a category slug and 0-1 sub-tags. Idempotent via updateOrCreate on body.
     * Depends on CategorySeeder having run first (questions FK -> categories).
     */
    public function run(): void
    {
        $path = __DIR__.'/data/questions_with_categories_pl.json';

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Cannot open seed file: {$path}");
        }

        /** @var list<array{body: string, category_slug: string, tags?: list<string>}> $entries */
        $entries = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        // Resolve slugs once to avoid a query per question.
        $categoryIds = Category::query()->pluck('id', 'slug');

        // Index of the first locked entry. max(0, ...) so a short deck (tests,
        // a trimmed file) locks everything rather than underflowing.
        $firstLockedIndex = max(0, count($entries) - self::LOCKED_TAIL);

        foreach ($entries as $index => $entry) {
            $body = trim($entry['body']);
            if ($body === '') {
                continue;
            }

            $categoryId = $categoryIds[$entry['category_slug']] ?? null;
            if ($categoryId === null) {
                throw new RuntimeException("Unknown category slug in seed data: {$entry['category_slug']}");
            }

            Question::updateOrCreate(
                ['body' => $body],
                [
                    'type' => 'session',
                    'locale' => 'pl',
                    'category_id' => $categoryId,
                    'tags' => $entry['tags'] ?? [],
                    'is_locked' => $index >= $firstLockedIndex,
                ],
            );
        }
    }
}
