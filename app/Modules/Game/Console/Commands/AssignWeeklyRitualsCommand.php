<?php

namespace App\Modules\Game\Console\Commands;

use App\Modules\Game\Actions\AssignWeeklyRitualAction;
use App\Modules\Game\Models\Couple;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Sunday cron: assign each couple this week's ritual. Runs at one global (UTC)
 * hour — unlike the daily card, the ritual's "day" is not local to the couple
 * (a ritual runs 7 days, a few hours' offset is irrelevant). Idempotent via
 * AssignWeeklyRitualAction (unique couple_id/started_on), so a re-run is safe.
 */
class AssignWeeklyRitualsCommand extends Command
{
    protected $signature = 'rituals:assign-weekly';

    protected $description = "Assign each couple this week's ritual (Sunday cron).";

    public function handle(): int
    {
        $sunday = CarbonImmutable::now()->startOfWeek(CarbonInterface::SUNDAY);

        $assigned = 0;

        // chunkById, not all(): the couples table must never be loaded whole into
        // memory by a recurring command.
        Couple::query()->chunkById(200, function (Collection $couples) use ($sunday, &$assigned): void {
            foreach ($couples as $couple) {
                AssignWeeklyRitualAction::run($couple, $sunday);
                $assigned++;
            }
        });

        $this->info("Weekly ritual assignment complete for {$assigned} couples (week of {$sunday->toDateString()}).");

        return self::SUCCESS;
    }
}
