<?php

use App\Modules\Catalog\Models\Question;
use Illuminate\Support\Facades\DB;

/*
 | The backfill migration — the one piece of (d) that production runs once, on
 | rows that already exist, and that the ordinary test run never exercises
 | (RefreshDatabase migrates an empty database, where the backfill is a no-op).
 |
 | The fixtures come from the migration's OWN frozen map rather than from the seed
 | file. That is deliberate: slice (c) is about to rewrite twenty question bodies,
 | and a test that compared the map against the current file would start failing
 | for the very reason the map was frozen.
 */

/** The migration object, loaded from its file the way the migrator loads it. */
function sessionBackfillMigration(): object
{
    return require database_path('migrations/2026_08_26_100001_backfill_seed_keys_for_session_questions.php');
}

/**
 * The body → seed_key map frozen inside the migration.
 *
 * @return array<string, string>
 */
function frozenSessionMap(): array
{
    /** @var array<string, string> $map */
    $map = (new ReflectionClass(sessionBackfillMigration()))->getReflectionConstant('MAP')->getValue();

    return $map;
}

/** Recreate the pre-(d) state: rows carrying the frozen text, no keys. */
function seedUnkeyedSessionDeck(): void
{
    foreach (frozenSessionMap() as $body) {
        Question::factory()->create(['body' => $body, 'type' => 'session', 'locale' => 'pl']);
    }
}

test('the backfill stamps every card it was frozen against', function (): void {
    seedUnkeyedSessionDeck();
    $idsBefore = Question::orderBy('id')->pluck('id')->all();

    sessionBackfillMigration()->up();

    expect(Question::whereNull('seed_key')->count())->toBe(0)
        // Identity was added to the rows that were there; nothing was created.
        ->and(Question::orderBy('id')->pluck('id')->all())->toBe($idsBefore);

    foreach (frozenSessionMap() as $seedKey => $body) {
        expect(Question::where('body', $body)->value('seed_key'))->toBe($seedKey);
    }
});

test('the backfill aborts when the text has moved on', function (): void {
    seedUnkeyedSessionDeck();

    // One card whose wording someone changed by hand since the map was frozen.
    $first = array_values(frozenSessionMap())[0];
    Question::where('body', $first)->update(['body' => 'Ktoś poprawił to pytanie ręcznie.']);

    sessionBackfillMigration()->up();
})->throws(RuntimeException::class, 'matched 99 of 100 rows by body');

test('the backfill aborts when a body is not unique', function (): void {
    seedUnkeyedSessionDeck();

    $first = array_values(frozenSessionMap())[0];
    Question::factory()->create(['body' => $first, 'type' => 'session', 'locale' => 'pl']);

    sessionBackfillMigration()->up();
})->throws(RuntimeException::class, 'appear more than once');

test('running the backfill twice stamps nothing twice', function (): void {
    seedUnkeyedSessionDeck();

    sessionBackfillMigration()->up();
    $keys = Question::orderBy('id')->pluck('seed_key', 'id')->all();

    sessionBackfillMigration()->up();

    expect(Question::orderBy('id')->pluck('seed_key', 'id')->all())->toBe($keys);
});

test('the backfill leaves an empty database alone', function (): void {
    sessionBackfillMigration()->up();

    expect(Question::count())->toBe(0);
});

test('the backfill ignores the daily deck', function (): void {
    seedUnkeyedSessionDeck();
    $daily = Question::factory()->create(['type' => 'daily', 'locale' => 'pl', 'category_id' => null]);

    sessionBackfillMigration()->up();

    // Each pool is stamped by its own migration, so a failure names the pool.
    expect($daily->refresh()->seed_key)->toBeNull()
        ->and(DB::table('questions')->where('type', 'session')->whereNull('seed_key')->count())->toBe(0);
});

test('the backfill refuses to leave a row in its pool without a key', function (): void {
    seedUnkeyedSessionDeck();

    // A row nobody knows about — not in the frozen map, so the per-file count
    // would have passed and this one would have slipped through unkeyed forever.
    Question::factory()->create([
        'body' => 'Pytanie dopisane ręcznie, poza seedem.',
        'type' => 'session',
        'locale' => 'pl',
    ]);

    sessionBackfillMigration()->up();
})->throws(RuntimeException::class, 'left 1 rows without a seed_key');
