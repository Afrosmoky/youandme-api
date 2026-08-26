<?php

namespace App\Modules\Catalog\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Give seed_keys to content entries that arrived without one.
 *
 * The job it exists for: Wiktoria exports a deck from her docx, the export
 * overwrites the seed file whole, and every key we stamped in slice (d) is gone
 * from the file. Numbering the survivors by hand is how a catalogue gets
 * duplicated, so this does it — and, more importantly, refuses to do it wrong.
 *
 * The rule that makes a re-export safe: a keyless entry is never minted a new key
 * if its text already belongs to a keyed one. It ADOPTS the key of the row that
 * already carries that text in the database. So an export that changed nothing
 * but the file mints nothing at all, and the catalogue stays the size it was.
 * Only genuinely new text gets a genuinely new number.
 *
 * Two entries in the same file sharing a body is a different animal — that is a
 * duplicate in the content, not a re-export — and it stops the command instead
 * of being papered over.
 *
 * Run by hand, never in a deploy: it edits a source file, and a deploy that
 * rewrites its own inputs is not a deploy. Without --write it only says what it
 * would do.
 */
class AssignSeedKeysCommand extends Command
{
    protected $signature = 'catalog:assign-keys {pool : session|daily|rituals} {--write : Save the file instead of only printing the plan}';

    protected $description = 'Assign seed_keys to content entries that have none (re-export safe).';

    /**
     * @var array<string, array{file: string, prefix: string, table: string, scope: array<string, string>}>
     */
    private const POOLS = [
        'session' => [
            'file' => 'questions_with_categories_pl.json',
            'prefix' => 'q',
            'table' => 'questions',
            'scope' => ['type' => 'session', 'locale' => 'pl'],
        ],
        'daily' => [
            'file' => 'daily_questions_pl.json',
            'prefix' => 'd',
            'table' => 'questions',
            'scope' => ['type' => 'daily', 'locale' => 'pl'],
        ],
        'rituals' => [
            'file' => 'rituals_pl.json',
            'prefix' => 'r',
            'table' => 'rituals',
            'scope' => ['locale' => 'pl'],
        ],
    ];

    public function handle(): int
    {
        $poolName = (string) $this->argument('pool');

        if (! isset(self::POOLS[$poolName])) {
            $this->error("Unknown pool '{$poolName}'. Use one of: ".implode(', ', array_keys(self::POOLS)).'.');

            return self::FAILURE;
        }

        $pool = self::POOLS[$poolName];
        $path = dirname(__DIR__, 2).'/Database/Seeders/data/'.$pool['file'];

        /** @var list<array<string, mixed>> $entries */
        $entries = $this->readEntries($path);

        // Keys already spoken for: in the file AND in the database. The database
        // half matters — a question retired from the content still owns its
        // number, and handing it to a new card would point old memories at new text.
        $keysInFile = $this->keysInFile($entries);
        $keysInDatabase = $this->keysInDatabase($pool);
        $nextNumber = $this->firstFreeNumber($pool['prefix'], array_merge($keysInFile, $keysInDatabase));

        $bodiesKeyedInFile = $this->bodiesKeyedInFile($entries);
        $bodiesKeyedInDatabase = $this->bodiesKeyedInDatabase($pool);

        $adopted = [];
        $minted = [];
        $problems = [];

        foreach ($entries as $index => $entry) {
            $body = trim((string) ($entry['body'] ?? ''));
            $position = $index + 1;

            if (trim((string) ($entry['seed_key'] ?? '')) !== '') {
                continue;
            }

            if ($body === '') {
                $problems[] = "position {$position}: empty body, nothing to key";

                continue;
            }

            if (isset($bodiesKeyedInFile[$body])) {
                $problems[] = sprintf(
                    'position %d: this text is already in the file under %s — a duplicate entry, not a new card',
                    $position,
                    $bodiesKeyedInFile[$body],
                );

                continue;
            }

            if (isset($bodiesKeyedInDatabase[$body])) {
                $key = $bodiesKeyedInDatabase[$body];
                $entries[$index]['seed_key'] = $key;
                $adopted[$key] = $body;

                continue;
            }

            $key = $pool['prefix'].str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
            $nextNumber++;
            $entries[$index]['seed_key'] = $key;
            $minted[$key] = $body;
            $bodiesKeyedInFile[$body] = $key;
        }

        $this->report($poolName, $adopted, $minted, $problems, count($entries));
        $this->warnAboutMissingFields($poolName, $entries);

        if ($problems !== []) {
            $this->error('Nothing was written. Resolve the entries above first.');

            return self::FAILURE;
        }

        if ($adopted === [] && $minted === []) {
            $this->info('Every entry already has a key — nothing to do.');

            return self::SUCCESS;
        }

        if (! $this->option('write')) {
            $this->newLine();
            $this->comment('Dry run. Re-run with --write to save the file.');

            return self::SUCCESS;
        }

        $this->writeEntries($path, $entries);
        $this->info("Saved {$pool['file']}.");

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readEntries(string $path): array
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Cannot open seed file: {$path}");
        }

        /** @var list<array<string, mixed>> $entries */
        $entries = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $entries;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function writeEntries(string $path, array $entries): void
    {
        // seed_key first, so a human opening the file sees the identity before the
        // text. Same encoding flags the files already use.
        $ordered = array_map(function (array $entry): array {
            $key = $entry['seed_key'] ?? null;
            unset($entry['seed_key']);

            return ['seed_key' => $key] + $entry;
        }, $entries);

        file_put_contents(
            $path,
            json_encode($ordered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
        );
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<string>
     */
    private function keysInFile(array $entries): array
    {
        $keys = [];

        foreach ($entries as $entry) {
            $key = trim((string) ($entry['seed_key'] ?? ''));
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  array{file: string, prefix: string, table: string, scope: array<string, string>}  $pool
     * @return list<string>
     */
    private function keysInDatabase(array $pool): array
    {
        /** @var list<string> $keys */
        $keys = DB::table($pool['table'])
            ->where($pool['scope'])
            ->whereNotNull('seed_key')
            ->pluck('seed_key')
            ->all();

        return $keys;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, string>
     */
    private function bodiesKeyedInFile(array $entries): array
    {
        $bodies = [];

        foreach ($entries as $entry) {
            $key = trim((string) ($entry['seed_key'] ?? ''));
            $body = trim((string) ($entry['body'] ?? ''));

            if ($key !== '' && $body !== '') {
                $bodies[$body] = $key;
            }
        }

        return $bodies;
    }

    /**
     * @param  array{file: string, prefix: string, table: string, scope: array<string, string>}  $pool
     * @return array<string, string>
     */
    private function bodiesKeyedInDatabase(array $pool): array
    {
        /** @var array<string, string> $bodies */
        $bodies = DB::table($pool['table'])
            ->where($pool['scope'])
            ->whereNotNull('seed_key')
            ->pluck('seed_key', 'body')
            ->all();

        return $bodies;
    }

    /**
     * @param  list<string>  $keys
     */
    private function firstFreeNumber(string $prefix, array $keys): int
    {
        $highest = 0;

        foreach ($keys as $key) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', $key, $matches) === 1) {
                $highest = max($highest, (int) $matches[1]);
            }
        }

        return $highest + 1;
    }

    /**
     * @param  array<string, string>  $adopted
     * @param  array<string, string>  $minted
     * @param  list<string>  $problems
     */
    private function report(string $pool, array $adopted, array $minted, array $problems, int $total): void
    {
        $this->line("Pool <info>{$pool}</info>: {$total} entries.");

        if ($adopted !== []) {
            $this->newLine();
            $this->line('<info>Adopted</info> (text already in the database under this key — nothing new created):');
            foreach ($adopted as $key => $body) {
                $this->line("  {$key}  ".$this->excerpt($body));
            }
        }

        if ($minted !== []) {
            $this->newLine();
            $this->line('<comment>New</comment> (text nothing in the database carries):');
            foreach ($minted as $key => $body) {
                $this->line("  {$key}  ".$this->excerpt($body));
            }
        }

        if ($problems !== []) {
            $this->newLine();
            $this->line('<error>Refused</error>:');
            foreach ($problems as $problem) {
                $this->line("  {$problem}");
            }
        }
    }

    /**
     * A card that arrives without the field deciding whether it is paid, or where
     * it sits in the ritual sequence, is a content decision nobody made. The
     * seeder would take the default; better to say so out loud while the file is
     * still open.
     *
     * @param  list<array<string, mixed>>  $entries
     */
    private function warnAboutMissingFields(string $pool, array $entries): void
    {
        $field = match ($pool) {
            'session' => 'is_locked',
            'rituals' => 'ordering',
            default => null,
        };

        if ($field === null) {
            return;
        }

        $missing = [];
        foreach ($entries as $entry) {
            if (! array_key_exists($field, $entry)) {
                $missing[] = trim((string) ($entry['seed_key'] ?? '?'));
            }
        }

        if ($missing !== []) {
            $this->newLine();
            $this->warn(sprintf(
                '%d entries have no %s: %s. The seeder will not guess — set it before seeding.',
                count($missing),
                $field,
                implode(', ', array_slice($missing, 0, 10)).(count($missing) > 10 ? ', …' : ''),
            ));
        }
    }

    private function excerpt(string $body): string
    {
        return mb_strimwidth($body, 0, 70, '…');
    }
}
