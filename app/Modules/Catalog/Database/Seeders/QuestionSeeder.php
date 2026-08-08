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
     *
     * Answer options (S2) come from a SECOND file, keyed by the same body. They are
     * deliberately not merged into questions_with_categories_pl.json: that file is
     * machine-produced from Wiktoria's docx and gets overwritten whole on the next
     * export, while the options come from another source and have to survive it.
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

        $options = $this->loadOptions();

        // Resolve slugs once to avoid a query per question.
        $categoryIds = Category::query()->pluck('id', 'slug');

        // Index of the first locked entry. max(0, ...) so a short deck (tests,
        // a trimmed file) locks everything rather than underflowing.
        $firstLockedIndex = max(0, count($entries) - self::LOCKED_TAIL);

        /** @var array<string, true> $matchedOptions */
        $matchedOptions = [];

        foreach ($entries as $index => $entry) {
            $body = trim($entry['body']);
            if ($body === '') {
                continue;
            }

            $categoryId = $categoryIds[$entry['category_slug']] ?? null;
            if ($categoryId === null) {
                throw new RuntimeException("Unknown category slug in seed data: {$entry['category_slug']}");
            }

            if (isset($options[$body])) {
                $matchedOptions[$body] = true;
            }

            Question::updateOrCreate(
                ['body' => $body],
                [
                    'type' => 'session',
                    'locale' => 'pl',
                    'category_id' => $categoryId,
                    'tags' => $entry['tags'] ?? [],
                    'is_locked' => $index >= $firstLockedIndex,
                    // Written unconditionally, null included: a card that loses its
                    // options in the file must lose them in the database too, or
                    // re-seeding would quietly keep serving the old list.
                    'options' => $options[$body] ?? null,
                ],
            );
        }

        // The two files are joined on a full question body, which is exactly the
        // string a content re-export is most likely to touch. An option entry that
        // matches nothing means the deck moved on without it — fail loudly here
        // rather than ship a card that silently lost its answers.
        $unmatched = array_diff(array_keys($options), array_keys($matchedOptions));
        if ($unmatched !== []) {
            throw new RuntimeException(
                'Options seed entries match no question body: '.implode(' | ', $unmatched)
            );
        }
    }

    /**
     * Answer options indexed by question body, already in the shape the column
     * stores: {items, multiple}. `multiple` says how the client lets the couple
     * pick (one option or several) — the answer itself stays plain text either
     * way, so nothing downstream of Catalog changes.
     *
     * @return array<string, array{items: list<string>, multiple: bool}>
     */
    private function loadOptions(): array
    {
        $path = __DIR__.'/data/question_options_pl.json';

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Cannot open seed file: {$path}");
        }

        /** @var list<array{body: string, options: list<string>, multiple: bool}> $entries */
        $entries = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $byBody = [];

        foreach ($entries as $entry) {
            $body = trim($entry['body']);

            if ($entry['options'] === []) {
                throw new RuntimeException("Empty options list in seed data for: {$body}");
            }

            $byBody[$body] = [
                'items' => $entry['options'],
                'multiple' => $entry['multiple'],
            ];
        }

        return $byBody;
    }
}
