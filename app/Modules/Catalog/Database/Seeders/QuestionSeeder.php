<?php

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use Illuminate\Database\Seeder;
use RuntimeException;

class QuestionSeeder extends Seeder
{
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

        /** @var list<array{body: string, category_slug: string, tags: list<string>}> $entries */
        $entries = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        // Resolve slugs once to avoid a query per question.
        $categoryIds = Category::query()->pluck('id', 'slug');

        foreach ($entries as $entry) {
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
                ],
            );
        }
    }
}
