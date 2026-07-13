<?php

namespace App\Modules\Game\Actions;

use App\Modules\Catalog\Queries\GetNextRitualIdQuery;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\CoupleWeeklyRitual;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Assign a couple its ritual for the week starting on $sunday. Owned by Game
 * (couple_weekly_rituals is Game state). Idempotent — the Sunday cron may fire
 * twice, and a couple already assigned for this Sunday is left untouched.
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

    public function handle(Couple $couple, CarbonImmutable $sunday): void
    {
        $startedOn = $sunday->toDateString();

        $alreadyAssigned = CoupleWeeklyRitual::query()
            ->where('couple_id', $couple->id)
            ->where('started_on', $startedOn)
            ->exists();

        if ($alreadyAssigned) {
            return;
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
            return;
        }

        CoupleWeeklyRitual::create([
            'couple_id' => $couple->id,
            'ritual_id' => $ritualId,
            'started_on' => $startedOn,
        ]);
    }
}
