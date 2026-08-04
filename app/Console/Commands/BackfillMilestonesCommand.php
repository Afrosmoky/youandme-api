<?php

namespace App\Console\Commands;

use App\Modules\Game\Models\Couple;
use App\Modules\Memories\Queries\CountMemoriesForCoupleQuery;
use App\Modules\Progress\Actions\CheckMilestonesAction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * One-off ops command: record the milestones that couples had already earned
 * before the progress map existed.
 *
 * Milestones are derived state, written by a listener on every played card — so
 * the day the feature ships, couples with history have a count well past several
 * thresholds and an empty register, and their map reads as untouched. It would
 * repair itself on their next card (CheckMilestonesAction records everything
 * crossed, not just the newest step), but "next card" may be weeks away, and the
 * map is exactly what they would open first. This is the standard backfill that
 * comes with introducing derived state into a system that already has a past.
 *
 * It lives in the app layer, not in Progress, for the same reason the listener
 * does: the count belongs to Memories and the register to Progress, and neither
 * knows the other. Progress receives the total as a value and stays a leaf.
 *
 * Idempotent — the register's composite primary key makes every write ON CONFLICT
 * DO NOTHING, so re-running is free and safe. Not scheduled: this is a migration
 * step, run by hand once.
 */
class BackfillMilestonesCommand extends Command
{
    protected $signature = 'progress:backfill-milestones';

    protected $description = 'Record milestones already earned by couples whose cards predate the progress map.';

    public function handle(): int
    {
        $couplesChecked = 0;
        $milestonesRecorded = 0;

        // chunkById, not all(): the couples table must never be loaded whole into
        // memory — same rule as the weekly ritual cron.
        Couple::query()->chunkById(200, function (Collection $couples) use (&$couplesChecked, &$milestonesRecorded): void {
            foreach ($couples as $couple) {
                $total = CountMemoriesForCoupleQuery::run($couple->id);

                // Every couple is checked, including those with nothing played:
                // skipping them would quietly diverge from the listener, which
                // never makes that assumption either.
                $milestonesRecorded += CheckMilestonesAction::run($couple->id, $total);
                $couplesChecked++;
            }
        });

        $this->info("Checked {$couplesChecked} couples, recorded {$milestonesRecorded} milestones.");

        return self::SUCCESS;
    }
}
