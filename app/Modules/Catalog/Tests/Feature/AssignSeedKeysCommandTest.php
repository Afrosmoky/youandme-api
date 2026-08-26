<?php

use App\Modules\Catalog\Models\Question;

/*
 | catalog:assign-keys — the door through which new content enters.
 |
 | Its real job is refusing to mint. A re-export of Wiktoria's docx arrives with
 | every key stripped; if the command numbered those entries afresh, the next seed
 | would create a second copy of the entire catalogue, which is precisely what
 | slice (d) removed.
 */

const SESSION_FILE = 'app/Modules/Catalog/Database/Seeders/data/questions_with_categories_pl.json';

/**
 * Run $work with the seed file temporarily replaced by $entries, then restore it.
 *
 * @param  list<array<string, mixed>>  $entries
 */
function withSeedFile(array $entries, Closure $work): void
{
    $path = base_path(SESSION_FILE);
    $original = (string) file_get_contents($path);

    file_put_contents($path, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    try {
        $work($path);
    } finally {
        file_put_contents($path, $original);
    }
}

/**
 * @return list<array<string, mixed>>
 */
function sessionEntries(): array
{
    /** @var list<array<string, mixed>> $entries */
    $entries = json_decode((string) file_get_contents(base_path(SESSION_FILE)), true, flags: JSON_THROW_ON_ERROR);

    return $entries;
}

test('a re-export with every key stripped adopts them all and mints nothing', function (): void {
    $this->seed();

    $stripped = array_map(function (array $entry): array {
        unset($entry['seed_key']);

        return $entry;
    }, sessionEntries());

    withSeedFile($stripped, function (string $path): void {
        $this->artisan('catalog:assign-keys', ['pool' => 'session', '--write' => true])
            ->expectsOutputToContain('Adopted')
            ->doesntExpectOutputToContain('New (text nothing in the database carries)')
            ->assertSuccessful();

        /** @var list<array{seed_key: string, body: string}> $written */
        $written = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        // Every entry recovered the key the database already holds for its text.
        foreach ($written as $entry) {
            expect(Question::where('body', $entry['body'])->value('seed_key'))->toBe($entry['seed_key']);
        }

        expect($written)->toHaveCount(100);
    });
});

test('genuinely new text gets the next free number', function (): void {
    $this->seed();

    $entries = sessionEntries();
    $entries[] = [
        'body' => 'Zupełnie nowe pytanie o konflikt, którego nikt wcześniej nie widział.',
        'category_slug' => 'na_poznanie',
        'tags' => [],
        'is_locked' => false,
    ];

    withSeedFile($entries, function (string $path): void {
        $this->artisan('catalog:assign-keys', ['pool' => 'session', '--write' => true])
            ->expectsOutputToContain('q101')
            ->assertSuccessful();

        /** @var list<array{seed_key: string}> $written */
        $written = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        expect(end($written)['seed_key'])->toBe('q101');
    });
});

test('a number already used in the database is never handed out again', function (): void {
    $this->seed();

    // A card retired from the content keeps its number — old memories point at it.
    Question::where('seed_key', 'q100')->firstOrFail()->update(['seed_key' => 'q140']);

    $entries = sessionEntries();
    $entries[] = ['body' => 'Nowe pytanie po wycofaniu innego.', 'category_slug' => 'randka', 'tags' => [], 'is_locked' => false];

    withSeedFile($entries, function (string $path): void {
        $this->artisan('catalog:assign-keys', ['pool' => 'session', '--write' => true])->assertSuccessful();

        /** @var list<array{seed_key: string}> $written */
        $written = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        // Not q101: the highest number in play is q140, wherever it lives.
        expect(end($written)['seed_key'])->toBe('q141');
    });
});

test('a duplicated body in the file is refused, not keyed', function (): void {
    $this->seed();

    $entries = sessionEntries();
    $duplicate = $entries[0];
    unset($duplicate['seed_key']);
    $entries[] = $duplicate;

    withSeedFile($entries, function (string $path): void {
        $before = (string) file_get_contents($path);

        $this->artisan('catalog:assign-keys', ['pool' => 'session', '--write' => true])
            ->expectsOutputToContain('Refused')
            ->assertFailed();

        // Refusal means refusal: the file is untouched.
        expect((string) file_get_contents($path))->toBe($before);
    });
});

test('without --write the command only says what it would do', function (): void {
    $this->seed();

    $stripped = array_map(function (array $entry): array {
        unset($entry['seed_key']);

        return $entry;
    }, sessionEntries());

    withSeedFile($stripped, function (string $path): void {
        $before = (string) file_get_contents($path);

        $this->artisan('catalog:assign-keys', ['pool' => 'session'])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        expect((string) file_get_contents($path))->toBe($before);
    });
});

test('a file that already has every key is left alone', function (): void {
    $this->seed();

    $this->artisan('catalog:assign-keys', ['pool' => 'session'])
        ->expectsOutputToContain('Every entry already has a key')
        ->assertSuccessful();
});

test('an unknown pool name is rejected', function (): void {
    $this->artisan('catalog:assign-keys', ['pool' => 'wspomnienia'])->assertFailed();
});
