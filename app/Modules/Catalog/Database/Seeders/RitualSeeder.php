<?php

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\Ritual;
use Illuminate\Database\Seeder;
use RuntimeException;

class RitualSeeder extends Seeder
{
    /**
     * Seed the ritual deck from Wiktoria's JSON (title + body). Idempotent via
     * updateOrCreate keyed on BODY, not title: two rituals share the title
     * "Tydzień zrozumienia" with different bodies, so keying on title would drop
     * one. ordering follows the order in the JSON.
     */
    public function run(): void
    {
        $path = __DIR__.'/data/rituals_pl.json';

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Cannot open seed file: {$path}");
        }

        /** @var list<array{title: string, body: string}> $entries */
        $entries = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        foreach ($entries as $ordering => $entry) {
            $body = trim($entry['body']);
            $title = trim($entry['title']);
            if ($body === '' || $title === '') {
                continue;
            }

            Ritual::updateOrCreate(
                ['body' => $body],
                [
                    'title' => $title,
                    'locale' => 'pl',
                    'ordering' => $ordering,
                ],
            );
        }
    }
}
