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
     * with a category slug and 0-1 sub-tags.
     *
     * Keyed on seed_key (q001..q100), never on body. That is the whole point of
     * slice (d): a question's text is content and content changes, so it cannot
     * also be the thing that says which row we mean. Since the key carries the
     * identity, `body` is now an ordinary updatable column — rewriting twenty
     * questions into neutral forms is a re-seed, not surgery.
     *
     * There is deliberately NO fallback to matching by body. A fallback would be
     * used exactly once — the first time a text changed — and would recreate the
     * duplicate this seeder exists to prevent. A file entry whose key is missing
     * from the database is simply a new question.
     *
     * is_locked is read from the file too, and REQUIRED there. It used to be
     * derived from position (the last 40 entries), which meant appending a new
     * category would silently re-draw the line between free and paid cards under
     * couples who had already paid. Positions carry no meaning here any more —
     * and neither does a default: whether a card is paid for is a decision
     * somebody makes, not one the seeder falls back into.
     *
     * Answer options come from a SECOND file, joined on the same seed_key. They
     * are deliberately not merged into questions_with_categories_pl.json: that
     * file is machine-produced from Wiktoria's docx and gets overwritten whole on
     * the next export, while the options come from another source and have to
     * survive it.
     *
     * Depends on CategorySeeder having run first (questions FK -> categories).
     */
    public function run(): void
    {
        $path = __DIR__.'/data/questions_with_categories_pl.json';

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Cannot open seed file: {$path}");
        }

        /** @var list<array{seed_key?: string, body: string, category_slug: string, tags?: list<string>, is_locked?: bool}> $entries */
        $entries = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $options = $this->loadOptions();

        // Resolve slugs once to avoid a query per question.
        $categoryIds = Category::query()->pluck('id', 'slug');

        /** @var array<string, true> $seenKeys */
        $seenKeys = [];
        /** @var array<string, true> $matchedOptions */
        $matchedOptions = [];

        foreach ($entries as $index => $entry) {
            $seedKey = trim($entry['seed_key'] ?? '');
            if ($seedKey === '') {
                throw new RuntimeException(
                    "Question at position {$index} has no seed_key. Run `php artisan catalog:assign-keys session` "
                    .'before seeding a file that came out of a content export.'
                );
            }

            if (isset($seenKeys[$seedKey])) {
                throw new RuntimeException("Duplicate seed_key in the session deck: {$seedKey}.");
            }
            $seenKeys[$seedKey] = true;

            $body = trim($entry['body']);
            if ($body === '') {
                throw new RuntimeException("Question {$seedKey} has an empty body.");
            }

            $categoryId = $categoryIds[$entry['category_slug']] ?? null;
            if ($categoryId === null) {
                throw new RuntimeException("Unknown category slug in seed data: {$entry['category_slug']}");
            }

            if (! array_key_exists('is_locked', $entry)) {
                throw new RuntimeException(
                    "Question {$seedKey} has no is_locked. Whether a card is paid for is a content decision and "
                    .'must be stated in the file — the seeder will not take a default for it.'
                );
            }

            if (isset($options[$seedKey])) {
                $matchedOptions[$seedKey] = true;
            }

            Question::updateOrCreate(
                ['seed_key' => $seedKey],
                [
                    'body' => $body,
                    'type' => 'session',
                    'locale' => 'pl',
                    'category_id' => $categoryId,
                    'tags' => $entry['tags'] ?? [],
                    'is_locked' => (bool) $entry['is_locked'],
                    // Written unconditionally, null included: a card that loses its
                    // options in the file must lose them in the database too, or
                    // re-seeding would quietly keep serving the old list.
                    'options' => $options[$seedKey] ?? null,
                ],
            );
        }

        $this->reportOrphans(array_keys($seenKeys));

        // An option entry pointing at a key no card carries means the two files
        // drifted apart — fail loudly here rather than ship a card that silently
        // lost its answers.
        $unmatched = array_diff(array_keys($options), array_keys($matchedOptions));
        if ($unmatched !== []) {
            throw new RuntimeException(
                'Options seed entries match no question seed_key: '.implode(', ', $unmatched)
            );
        }
    }

    /**
     * Say something about rows the file no longer mentions.
     *
     * A question dropped from a content export is not deleted here and never will
     * be by a seeder: couples' memories, played cards and likes point at it, and
     * removing it would take their history with it. But it also stops being
     * maintained the moment it leaves the file, and nothing else would ever
     * mention it again — so the seeder says its name out loud and leaves the
     * decision to a person.
     *
     * @param  list<string>  $keysInFile
     */
    private function reportOrphans(array $keysInFile): void
    {
        /** @var list<string> $orphans */
        $orphans = Question::query()
            ->where('type', 'session')
            ->where('locale', 'pl')
            ->whereNotNull('seed_key')
            ->whereNotIn('seed_key', $keysInFile)
            ->orderBy('seed_key')
            ->pluck('seed_key')
            ->all();

        if ($orphans === [] || $this->command === null) {
            return;
        }

        $this->command->warn(sprintf(
            '%d session questions are in the database but no longer in the seed file: %s. Left untouched — '
            .'couples still have history on them.',
            count($orphans),
            implode(', ', $orphans),
        ));
    }

    /**
     * Answer options indexed by seed_key, already in the shape the column stores:
     * {items, multiple}. `multiple` says how the client lets the couple pick (one
     * option or several) — the answer itself stays plain text either way, so
     * nothing downstream of Catalog changes.
     *
     * The file keeps a `body` next to the key for whoever reads it, but the join
     * is the key. Before (d) the body WAS the join, which made every option entry
     * a hostage to its question's wording.
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

        /** @var list<array{seed_key?: string, options: list<string>, multiple: bool}> $entries */
        $entries = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $byKey = [];

        foreach ($entries as $index => $entry) {
            $seedKey = trim($entry['seed_key'] ?? '');
            if ($seedKey === '') {
                throw new RuntimeException("Options entry at position {$index} has no seed_key.");
            }

            if ($entry['options'] === []) {
                throw new RuntimeException("Empty options list in seed data for: {$seedKey}");
            }

            $byKey[$seedKey] = [
                'items' => $entry['options'],
                'multiple' => $entry['multiple'],
            ];
        }

        return $byKey;
    }
}
