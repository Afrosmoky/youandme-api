<?php

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\Ritual;
use Illuminate\Database\Seeder;
use RuntimeException;

class RitualSeeder extends Seeder
{
    /**
     * Seed the ritual deck from Wiktoria's JSON (title + body). Keyed on seed_key
     * (r001..r033), never on body, with no fallback — same rule as both question
     * decks.
     *
     * This one had the workaround written into it: it keyed on BODY rather than
     * title because two rituals share the title "Tydzień zrozumienia". With a real
     * key that stops being a consideration; titles may repeat all they like.
     *
     * ordering is read from the file instead of taken from the array index. It is
     * not cosmetic: AssignWeeklyRitualAction hands out the lowest-ordering ritual
     * a couple has not had yet, so the index quietly decided the order in which
     * every couple walks the deck — and a reordered export would have re-dealt it
     * underneath them.
     */
    public function run(): void
    {
        $path = __DIR__.'/data/rituals_pl.json';

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Cannot open seed file: {$path}");
        }

        /** @var list<array{seed_key?: string, title: string, body: string, ordering?: int}> $entries */
        $entries = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        /** @var array<string, true> $seenKeys */
        $seenKeys = [];

        foreach ($entries as $index => $entry) {
            $seedKey = trim($entry['seed_key'] ?? '');
            if ($seedKey === '') {
                throw new RuntimeException(
                    "Ritual at position {$index} has no seed_key. Run "
                    .'`php artisan catalog:assign-keys rituals` before seeding an exported file.'
                );
            }

            if (isset($seenKeys[$seedKey])) {
                throw new RuntimeException("Duplicate seed_key in the ritual deck: {$seedKey}.");
            }
            $seenKeys[$seedKey] = true;

            $body = trim($entry['body']);
            $title = trim($entry['title']);
            if ($body === '' || $title === '') {
                throw new RuntimeException("Ritual {$seedKey} has an empty title or body.");
            }

            if (! isset($entry['ordering'])) {
                throw new RuntimeException("Ritual {$seedKey} has no ordering — it decides the deck's sequence.");
            }

            Ritual::updateOrCreate(
                ['seed_key' => $seedKey],
                [
                    'title' => $title,
                    'body' => $body,
                    'locale' => 'pl',
                    'ordering' => (int) $entry['ordering'],
                ],
            );
        }

        $this->reportOrphans(array_keys($seenKeys));
    }

    /**
     * Rituals the file no longer mentions. Not deleted — couple_weekly_rituals
     * records which ritual a couple had in a given week, and a removed row would
     * take that week with it.
     *
     * @param  list<string>  $keysInFile
     */
    private function reportOrphans(array $keysInFile): void
    {
        /** @var list<string> $orphans */
        $orphans = Ritual::query()
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
            '%d rituals are in the database but no longer in the seed file: %s. Left untouched — couples have '
            .'weeks recorded against them.',
            count($orphans),
            implode(', ', $orphans),
        ));
    }
}
