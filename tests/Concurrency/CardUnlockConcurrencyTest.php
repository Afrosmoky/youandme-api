<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Rewards\Actions\GrantCreditsAction;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Models\User;

/*
 | Two phones of one couple spending the same balance at the same moment. These
 | run as REAL parallel processes (pcntl_fork), each with its own Postgres
 | connection, because a race cannot be shown inside one connection — and without
 | RefreshDatabase (DatabaseTruncation instead, see tests/Pest.php): rows held in a
 | test transaction would be invisible to the children.
 |
 | Overlap is forced, not hoped for: the parent holds a lock the children must
 | queue on (the balance row, or an uncommitted entitlement row), waits until
 | Postgres reports both children waiting, and only then lets go. Whatever the
 | scheduler does, both requests are inside their transactions at once.
 |
 | Hard conditions under test: the balance never goes below zero, and no card is
 | ever paid for twice.
 */

/**
 * A couple with two users (two phones) and a balance.
 *
 * @return array{0: User, 1: User, 2: int}
 */
function coupleWithTwoPhones(int $credits): array
{
    $first = createUserWithCouple();
    $couple = activeCoupleOf($first);
    $second = User::factory()->create();
    $second->active_couple_id = $couple->id;
    $second->save();
    $couple->user_b_id = $second->id;
    $couple->save();

    GrantCreditsAction::run($couple->id, $credits);

    return [$first, $second, $couple->id];
}

/**
 * A second connection to the test database, separate from the default one.
 */
function sideConnection(string $name): Connection
{
    config(["database.connections.{$name}" => config('database.connections.'.config('database.default'))]);

    return DB::connection($name);
}

/**
 * Number of sessions on the test database currently waiting for a lock.
 */
function sessionsWaitingForALock(Connection $probe): int
{
    return (int) $probe->scalar(
        "SELECT count(*) FROM pg_stat_activity
         WHERE datname = current_database() AND wait_event_type = 'Lock' AND pid <> pg_backend_pid()"
    );
}

/**
 * Run each job in its own forked process while $holdLock keeps a lock the jobs
 * queue on, then release it.
 *
 * Jobs start one at a time: the next is forked only once the previous one is
 * seen waiting. That makes the lock queue order the job order — deterministic,
 * not up to the scheduler — so a test can put a request first in line on purpose.
 *
 * Children never exit normally: they write their result and SIGKILL themselves,
 * so no destructor runs in them — an inherited PDO (the parent's lock connection)
 * closed from a child would terminate the parent's session.
 *
 * @param  list<Closure(): array<string, mixed>>  $jobs
 * @param  Closure(Connection): void  $holdLock  runs inside an open transaction
 * @return list<array<string, mixed>>
 */
function runWhileLocked(array $jobs, Closure $holdLock): array
{
    // Children must open their own connections, not share the parent's socket.
    DB::disconnect();

    $lock = sideConnection('concurrency_lock');
    $lock->beginTransaction();
    $holdLock($lock);

    $children = [];
    $probe = null;

    foreach ($jobs as $index => $job) {
        $file = tempnam(sys_get_temp_dir(), 'unlock-race-');
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('pcntl_fork failed');
        }

        if ($pid === 0) {
            try {
                $result = $job();
            } catch (Throwable $e) {
                $result = ['exception' => $e::class.': '.$e->getMessage()];
            }

            file_put_contents($file, json_encode($result));
            posix_kill(posix_getpid(), SIGKILL);
        }

        $children[] = [$pid, $file];

        // Opened after the first fork, so no child holds a copy of it.
        $probe ??= sideConnection('concurrency_probe');
        $deadline = microtime(true) + 15;

        while (sessionsWaitingForALock($probe) < $index + 1 && microtime(true) < $deadline) {
            usleep(10_000);
        }
    }

    $waiting = sessionsWaitingForALock($probe);
    $lock->rollBack();

    $results = [];

    foreach ($children as [$pid, $file]) {
        pcntl_waitpid($pid, $status);
        $results[] = json_decode((string) file_get_contents($file), true);
        unlink($file);
    }

    DB::purge('concurrency_lock');
    DB::purge('concurrency_probe');

    expect($waiting)->toBe(count($jobs), 'the requests never all queued at once — the race was not exercised');

    return $results;
}

/**
 * @param  list<string>  $ulids
 * @return Closure(): array<string, mixed>
 */
function bulkUnlockAs(object $test, User $user, array $ulids): Closure
{
    return function () use ($test, $user, $ulids): array {
        Sanctum::actingAs($user);
        $response = $test->postJson('/api/v1/questions/unlock', ['question_ulids' => $ulids]);

        return ['status' => $response->status(), 'body' => $response->json()];
    };
}

/**
 * Hold the couple's balance row, so both requests queue on the debit after their
 * entitlement inserts.
 *
 * @return Closure(Connection): void
 */
function holdingBalanceOf(int $coupleId): Closure
{
    return function (Connection $lock) use ($coupleId): void {
        $lock->select('SELECT credits FROM couple_rewards WHERE couple_id = ? FOR UPDATE', [$coupleId]);
    };
}

afterEach(function (): void {
    // Rows here are committed for real. Leave nothing behind for a later suite.
    $this->truncateTablesForAllConnections();
});

test('two phones cannot overdraw one balance', function (): void {
    [$first, $second, $coupleId] = coupleWithTwoPhones(credits: 3);
    $cards = Question::factory()->locked()->count(4)->create();

    $results = runWhileLocked([
        bulkUnlockAs($this, $first, [$cards[0]->ulid, $cards[1]->ulid]),
        bulkUnlockAs($this, $second, [$cards[2]->ulid, $cards[3]->ulid]),
    ], holdingBalanceOf($coupleId));

    $statuses = array_column($results, 'status');
    sort($statuses);

    // 3 credits cover one request of 2, never both: one wins, one is refused whole.
    expect($statuses)->toBe([200, 422])
        ->and(creditsOfCouple($coupleId))->toBe(1)
        ->and(DB::table('couple_unlocked_questions')->where('couple_id', $coupleId)->count())->toBe(2);
});

test('the same cards bought from two phones are paid for once', function (): void {
    [$first, $second, $coupleId] = coupleWithTwoPhones(credits: 5);
    $cards = Question::factory()->locked()->count(2)->create();
    $ulids = $cards->pluck('ulid')->all();

    $results = runWhileLocked([
        bulkUnlockAs($this, $first, $ulids),
        bulkUnlockAs($this, $second, $ulids),
    ], holdingBalanceOf($coupleId));

    expect(array_column($results, 'status'))->toBe([200, 200])
        ->and(array_sum(array_map(fn (array $r): int => $r['body']['charged'], $results)))->toBe(2)
        ->and(creditsOfCouple($coupleId))->toBe(3)
        ->and(DB::table('couple_unlocked_questions')->where('couple_id', $coupleId)->count())->toBe(2);
});

test('a single unlock and a bulk unlock of the same card charge it once', function (): void {
    [$first, $second, $coupleId] = coupleWithTwoPhones(credits: 5);
    [$shared, $other] = Question::factory()->locked()->count(2)->create();

    $single = function () use ($first, $shared): array {
        Sanctum::actingAs($first);
        $response = $this->postJson("/api/v1/questions/{$shared->ulid}/unlock");

        return ['status' => $response->status(), 'body' => $response->json()];
    };

    $results = runWhileLocked([
        $single,
        bulkUnlockAs($this, $second, [$other->ulid, $shared->ulid]),
    ], holdingBalanceOf($coupleId));

    // Whoever wins the shared card pays for it; the other is never charged for it.
    // No deadlock (that would be a 500): the balance is the last lock in both.
    expect(array_column($results, 'status'))->each->toBeIn([200, 409])
        ->and(creditsOfCouple($coupleId))->toBe(3)
        ->and(DB::table('couple_unlocked_questions')->where('couple_id', $coupleId)->count())->toBe(2);
});

test('lists in opposite order queue instead of deadlocking', function (): void {
    [$first, $second, $coupleId] = coupleWithTwoPhones(credits: 5);
    [$low, $high] = Question::factory()->locked()->count(2)->create()->sortBy('id')->values()->all();

    // The parent holds an uncommitted entitlement row for the HIGHER id, so both
    // requests are mid-INSERT at once over the same two cards, listed in opposite
    // order. Neither may end in a 500 (a deadlock) and the two cards are paid for
    // exactly once.
    //
    // This cannot force the deadlock an unsorted insert would allow: a unique-key
    // waiter waits for the holder's whole transaction, every waiter wakes when it
    // ends, and which one goes next is Postgres's call. The ordering itself is
    // pinned deterministically in UnlockQuestionsForCoupleActionTest; this proves
    // the path under real contention.
    $holdHighCard = function (Connection $lock) use ($coupleId, $high): void {
        $lock->insert(
            'INSERT INTO couple_unlocked_questions (couple_id, question_id, unlocked_at, source) VALUES (?, ?, now(), ?)',
            [$coupleId, $high->id, 'credits'],
        );
    };

    $results = runWhileLocked([
        bulkUnlockAs($this, $first, [$high->ulid, $low->ulid]),
        bulkUnlockAs($this, $second, [$low->ulid, $high->ulid]),
    ], $holdHighCard);

    expect(array_column($results, 'status'))->toBe([200, 200])
        ->and(array_sum(array_map(fn (array $r): int => $r['body']['charged'], $results)))->toBe(2)
        ->and(creditsOfCouple($coupleId))->toBe(3)
        ->and(DB::table('couple_unlocked_questions')->where('couple_id', $coupleId)->count())->toBe(2);
});
