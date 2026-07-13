<?php

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\Question;
use Illuminate\Database\Seeder;
use RuntimeException;

class DailyQuestionSeeder extends Seeder
{
    /**
     * Seed the daily-card deck: 100 short questions with type='daily'. Modelled
     * 1:1 on QuestionSeeder. Daily questions have no category and no tags — they
     * are a separate pool from the session deck (GetSessionQuestionPoolQuery
     * filters type='session', so these never pollute a game session). Idempotent
     * via updateOrCreate on body.
     */
    public function run(): void
    {
        $path = __DIR__.'/data/daily_questions_pl.json';

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Cannot open seed file: {$path}");
        }

        /** @var list<array{body: string}> $entries */
        $entries = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        foreach ($entries as $entry) {
            $body = trim($entry['body']);
            if ($body === '') {
                continue;
            }

            Question::updateOrCreate(
                ['body' => $body],
                [
                    'type' => 'daily',
                    'locale' => 'pl',
                    'category_id' => null,
                    'tags' => [],
                ],
            );
        }
    }
}
