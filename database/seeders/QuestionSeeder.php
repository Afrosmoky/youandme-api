<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Seeder;
use RuntimeException;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeds/questions_session_pl.csv');

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open seed file: {$path}");
        }

        $header = fgetcsv($handle, escape: '');
        if ($header === false || ($header[0] ?? null) !== 'body') {
            fclose($handle);
            throw new RuntimeException("Invalid CSV header in {$path}, expected 'body'");
        }

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $body = trim((string) ($row[0] ?? ''));
            if ($body === '') {
                continue;
            }

            Question::firstOrCreate(
                ['body' => $body],
                ['type' => 'session', 'locale' => 'pl'],
            );
        }

        fclose($handle);
    }
}
