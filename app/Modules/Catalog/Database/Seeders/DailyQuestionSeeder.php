<?php

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\Question;
use Illuminate\Database\Seeder;
use RuntimeException;

class DailyQuestionSeeder extends Seeder
{
    /**
     * Seed the daily-card deck: 100 short questions with type='daily'. Modelled
     * 1:1 on QuestionSeeder, including its identity rule — keyed on seed_key
     * (d001..d100), never on body, with no fallback.
     *
     * The prefix is not decoration: `questions` holds both decks in one table
     * under one unique index, and before (d) this seeder matched on body ALONE,
     * without even filtering by type. A daily question that happened to share its
     * wording with a session card would have flipped that card's type and pulled
     * it out of the game deck. The key makes that impossible to express.
     *
     * Daily questions have no category and no tags — they are a separate pool from
     * the session deck (GetSessionQuestionPoolQuery filters type='session', so
     * these never pollute a game session) and they are never locked (P7: the daily
     * deck is free).
     */
    public function run(): void
    {
        $path = __DIR__.'/data/daily_questions_pl.json';

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Cannot open seed file: {$path}");
        }

        /** @var list<array{seed_key?: string, body: string}> $entries */
        $entries = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        /** @var array<string, true> $seenKeys */
        $seenKeys = [];

        foreach ($entries as $index => $entry) {
            $seedKey = trim($entry['seed_key'] ?? '');
            if ($seedKey === '') {
                throw new RuntimeException(
                    "Daily question at position {$index} has no seed_key. Run "
                    .'`php artisan catalog:assign-keys daily` before seeding an exported file.'
                );
            }

            if (isset($seenKeys[$seedKey])) {
                throw new RuntimeException("Duplicate seed_key in the daily deck: {$seedKey}.");
            }
            $seenKeys[$seedKey] = true;

            $body = trim($entry['body']);
            if ($body === '') {
                throw new RuntimeException("Daily question {$seedKey} has an empty body.");
            }

            Question::updateOrCreate(
                ['seed_key' => $seedKey],
                [
                    'body' => $body,
                    'type' => 'daily',
                    'locale' => 'pl',
                    'category_id' => null,
                    'tags' => [],
                ],
            );
        }

        $this->reportOrphans(array_keys($seenKeys));
    }

    /**
     * Daily questions the file no longer mentions. Not deleted — couples answered
     * them, and those memories point here. Named out loud instead, so a card
     * dropped from an export does not simply go quiet.
     *
     * @param  list<string>  $keysInFile
     */
    private function reportOrphans(array $keysInFile): void
    {
        /** @var list<string> $orphans */
        $orphans = Question::query()
            ->where('type', 'daily')
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
            '%d daily questions are in the database but no longer in the seed file: %s. Left untouched.',
            count($orphans),
            implode(', ', $orphans),
        ));
    }
}
