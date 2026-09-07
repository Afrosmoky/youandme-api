<?php

namespace App\Modules\Game\Actions;

use App\Modules\Catalog\Queries\GetNextRitualIdQuery;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\CoupleWeeklyRitual;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Assign a couple its ritual for the week starting on $sunday. Owned by Game
 * (couple_weekly_rituals is Game state). Returns the assignment for that week —
 * the caller's own, or the one that was already there. Null only when no rituals
 * are seeded at all.
 *
 * Idempotent, and now race-safe as well. Check-then-insert alone was enough while
 * the lazy path fired once in a couple's lifetime; since the read was narrowed to
 * the current week it fires every week for every couple whose local Sunday opens
 * before the 00:30 UTC cron (everything east of UTC), so the window between the
 * check and the insert is one a real request can land in. The collision is caught
 * narrowly — only a unique violation, and only when re-reading the row explains
 * it. Anything else, including a unique violation that leaves nothing behind,
 * propagates: an outage must stay loud (that is the whole lesson of this fix).
 *
 * Anti-repeat is enforced here, not by the database: exclude every ritual the
 * couple has already had and take the lowest-ordering one left. When the pool is
 * exhausted the couple restarts the cycle from the ritual it used longest ago —
 * which, because the first cycle fills by ordering, reproduces the ordering
 * sequence, so no cycle marker is needed.
 */
final class AssignWeeklyRitualAction
{
    use AsAction;

    public function handle(Couple $couple, CarbonImmutable $sunday): ?CoupleWeeklyRitual
    {
        $startedOn = $sunday->toDateString();

        $existing = $this->assignmentFor($couple, $startedOn);

        if ($existing !== null) {
            return $existing;
        }

        /** @var list<int> $excludeIds */
        $excludeIds = CoupleWeeklyRitual::query()
            ->where('couple_id', $couple->id)
            ->distinct()
            ->pluck('ritual_id')
            ->all();

        $ritualId = GetNextRitualIdQuery::run($excludeIds);

        if ($ritualId === null) {
            // Pool exhausted → restart: the ritual whose most recent assignment is
            // the oldest (used longest ago).
            $oldest = CoupleWeeklyRitual::query()
                ->where('couple_id', $couple->id)
                ->groupBy('ritual_id')
                ->orderByRaw('MAX(started_on) ASC')
                ->value('ritual_id');

            $ritualId = $oldest !== null ? (int) $oldest : null;
        }

        if ($ritualId === null) {
            // No rituals seeded at all — nothing to assign.
            return null;
        }

        try {
            // The insert gets its own transaction — a SAVEPOINT when there is
            // already one open. Postgres invalidates the WHOLE transaction on any
            // error, so without this the re-read below would die on 25P02
            // ("current transaction is aborted") instead of finding the winner's
            // row. That is not merely a test artefact: it is what would happen the
            // day someone calls this Action from inside a DB::transaction.
            return DB::transaction(fn (): CoupleWeeklyRitual => CoupleWeeklyRitual::create([
                'couple_id' => $couple->id,
                'ritual_id' => $ritualId,
                'started_on' => $startedOn,
            ]));
        } catch (UniqueConstraintViolationException $e) {
            // Someone (the cron, or a parallel request) inserted this couple's row
            // for this week between our check and our insert. Re-read with the same
            // equality the check used: if the row is there, the collision was the
            // unique on (couple_id, started_on) doing its job and their row wins.
            $winner = $this->assignmentFor($couple, $startedOn);

            if ($winner === null) {
                // A unique violation that no assignment explains is a different
                // fault wearing the same exception. Do not swallow it.
                throw $e;
            }

            return $winner;
        }
    }

    private function assignmentFor(Couple $couple, string $startedOn): ?CoupleWeeklyRitual
    {
        return CoupleWeeklyRitual::query()
            ->where('couple_id', $couple->id)
            ->where('started_on', $startedOn)
            ->first();
    }
}
