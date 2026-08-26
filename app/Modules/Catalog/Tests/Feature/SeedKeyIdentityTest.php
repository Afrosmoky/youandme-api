<?php

use App\Modules\Catalog\Database\Seeders\QuestionSeeder;
use App\Modules\Catalog\Database\Seeders\RitualSeeder;
use App\Modules\Catalog\Models\Question;
use App\Modules\Catalog\Models\Ritual;
use App\Modules\Game\Models\Couple;
use App\Modules\Memories\Models\Memory;
use Illuminate\Support\Facades\DB;

/*
 | Slice (d): content is identified by seed_key, not by its own text.
 |
 | The test this whole slice exists for is the first one — a question whose
 | wording changes keeps its row, and every couple's like, played card and memory
 | keeps pointing at it. Before (d) that edit created a second row and left the
 | couples attached to the old one.
 */

/** Rewrite one entry's body in a seed file, run the seeder, restore the file. */
function reseedWithEditedBody(string $file, string $seedKey, string $newBody, string $seederClass): void
{
    $path = base_path("app/Modules/Catalog/Database/Seeders/data/{$file}");
    $original = (string) file_get_contents($path);

    /** @var list<array<string, mixed>> $entries */
    $entries = json_decode($original, true, flags: JSON_THROW_ON_ERROR);

    foreach ($entries as $index => $entry) {
        if (($entry['seed_key'] ?? null) === $seedKey) {
            $entries[$index]['body'] = $newBody;
        }
    }

    file_put_contents($path, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    try {
        (new $seederClass)->run();
    } finally {
        file_put_contents($path, $original);
    }
}

test('rewriting a question keeps its row and every couple attachment', function (): void {
    $this->seed();

    $question = Question::where('seed_key', 'q021')->firstOrFail();
    $couple = Couple::factory()->create();

    // One row of each kind that points at this question.
    $couple->likedQuestions()->attach($question->id, ['liked_at' => now()]);
    $couple->seenQuestions()->attach($question->id, ['seen_at' => now()]);
    $couple->unlockedQuestions()->attach($question->id, ['unlocked_at' => now(), 'source' => 'credits']);
    $memory = Memory::factory()->create(['couple_id' => $couple->id, 'question_id' => $question->id]);

    $before = ['id' => $question->id, 'ulid' => $question->ulid, 'count' => Question::count()];

    reseedWithEditedBody(
        'questions_with_categories_pl.json',
        'q021',
        'Zupełnie inna treść tego samego pytania.',
        QuestionSeeder::class,
    );

    $after = Question::where('seed_key', 'q021')->firstOrFail();

    expect($after->id)->toBe($before['id'])
        ->and($after->ulid)->toBe($before['ulid'])
        ->and($after->body)->toBe('Zupełnie inna treść tego samego pytania.')
        // No second row: the deck is the same size it was.
        ->and(Question::count())->toBe($before['count'])
        // And every attachment still lands on it.
        ->and($couple->likedQuestions()->where('questions.id', $before['id'])->exists())->toBeTrue()
        ->and($couple->seenQuestions()->where('questions.id', $before['id'])->exists())->toBeTrue()
        ->and($couple->unlockedQuestions()->where('questions.id', $before['id'])->exists())->toBeTrue()
        ->and($memory->refresh()->question_id)->toBe($before['id']);
});

test('rewriting a ritual keeps its row and the couples assigned to it', function (): void {
    $this->seed();

    $ritual = Ritual::where('seed_key', 'r001')->firstOrFail();
    $couple = Couple::factory()->create();
    DB::table('couple_weekly_rituals')->insert([
        'couple_id' => $couple->id,
        'ritual_id' => $ritual->id,
        'started_on' => '2026-08-23',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $before = ['id' => $ritual->id, 'ulid' => $ritual->ulid, 'count' => Ritual::count()];

    reseedWithEditedBody(
        'rituals_pl.json',
        'r001',
        'Przepisana instrukcja rytuału.',
        RitualSeeder::class,
    );

    $after = Ritual::where('seed_key', 'r001')->firstOrFail();

    expect($after->id)->toBe($before['id'])
        ->and($after->ulid)->toBe($before['ulid'])
        ->and($after->body)->toBe('Przepisana instrukcja rytuału.')
        ->and(Ritual::count())->toBe($before['count'])
        ->and(DB::table('couple_weekly_rituals')->where('ritual_id', $before['id'])->count())->toBe(1);
});

test('every seeded row carries a key and the keys are unique per pool', function (): void {
    $this->seed();

    expect(Question::whereNull('seed_key')->count())->toBe(0)
        ->and(Ritual::whereNull('seed_key')->count())->toBe(0)
        ->and(Question::where('type', 'session')->count())->toBe(100)
        ->and(Question::where('type', 'daily')->count())->toBe(100)
        ->and(Ritual::count())->toBe(33);

    // The prefix is what keeps the two decks apart under one global unique index.
    expect(Question::where('type', 'session')->where('seed_key', 'like', 'q%')->count())->toBe(100)
        ->and(Question::where('type', 'daily')->where('seed_key', 'like', 'd%')->count())->toBe(100)
        ->and(Ritual::where('seed_key', 'like', 'r%')->count())->toBe(33);
});

test('is_locked comes from the file, not from the entry position', function (): void {
    $this->seed();

    $path = base_path('app/Modules/Catalog/Database/Seeders/data/questions_with_categories_pl.json');
    /** @var list<array{seed_key: string, is_locked: bool}> $entries */
    $entries = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    $lockedInFile = array_values(array_map(
        fn (array $entry): string => $entry['seed_key'],
        array_filter($entries, fn (array $entry): bool => $entry['is_locked'] === true),
    ));

    $lockedInDatabase = Question::where('type', 'session')->where('is_locked', true)
        ->orderBy('seed_key')->pluck('seed_key')->all();

    sort($lockedInFile);

    // The paid half of the deck is now data. It used to be "the last forty
    // entries", which meant appending a category silently re-drew the line under
    // couples who had already paid.
    expect($lockedInDatabase)->toBe($lockedInFile)
        ->and($lockedInFile)->toHaveCount(40);
});

test('a card keeps its lock when the file is reordered', function (): void {
    $this->seed();

    $path = base_path('app/Modules/Catalog/Database/Seeders/data/questions_with_categories_pl.json');
    $original = (string) file_get_contents($path);

    /** @var list<array<string, mixed>> $entries */
    $entries = json_decode($original, true, flags: JSON_THROW_ON_ERROR);
    file_put_contents($path, json_encode(array_reverse($entries), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    try {
        (new QuestionSeeder)->run();
    } finally {
        file_put_contents($path, $original);
    }

    // q100 was the last entry and paid; reversed it is the first, and still paid.
    expect(Question::where('seed_key', 'q100')->firstOrFail()->is_locked)->toBeTrue()
        ->and(Question::where('seed_key', 'q001')->firstOrFail()->is_locked)->toBeFalse()
        ->and(Question::where('type', 'session')->where('is_locked', true)->count())->toBe(40);
});

test('ritual ordering comes from the file, not the array index', function (): void {
    $this->seed();

    $path = base_path('app/Modules/Catalog/Database/Seeders/data/rituals_pl.json');
    /** @var list<array{seed_key: string, ordering: int}> $entries */
    $entries = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    foreach ($entries as $entry) {
        expect(Ritual::where('seed_key', $entry['seed_key'])->value('ordering'))->toBe($entry['ordering']);
    }
});

test('options attach by key, so a rewritten question keeps its answers', function (): void {
    $this->seed();

    $before = Question::where('seed_key', 'q001')->firstOrFail();
    expect($before->options)->not->toBeNull();

    reseedWithEditedBody(
        'questions_with_categories_pl.json',
        'q001',
        'Pytanie z opcjami, przepisane innymi słowami.',
        QuestionSeeder::class,
    );

    // Before (d) the options file was joined on the full body, so this edit would
    // have detached the answers — or blown the seeder up on the orphan check.
    expect(Question::where('seed_key', 'q001')->firstOrFail()->options)->toBe($before->options);
});

test('re-seeding twice changes nothing', function (): void {
    $this->seed();
    $snapshot = Question::orderBy('id')->pluck('body', 'seed_key')->all();
    $count = Question::count();

    $this->seed();

    expect(Question::count())->toBe($count)
        ->and(Question::orderBy('id')->pluck('body', 'seed_key')->all())->toBe($snapshot);
});

test('the seeder names rows the file no longer mentions instead of removing them', function (): void {
    $this->seed();

    // A card retired from the content, with a couple's history on it.
    $retired = Question::where('seed_key', 'q100')->firstOrFail();
    $retired->update(['seed_key' => 'q900']);
    $couple = Couple::factory()->create();
    $couple->seenQuestions()->attach($retired->id, ['seen_at' => now()]);

    $this->artisan('db:seed', ['--class' => QuestionSeeder::class])
        ->expectsOutputToContain('q900')
        ->assertSuccessful();

    // Still there, still attached: a seeder does not get to delete a couple's past.
    expect(Question::where('seed_key', 'q900')->exists())->toBeTrue()
        ->and($couple->seenQuestions()->where('questions.id', $retired->id)->exists())->toBeTrue();
});
