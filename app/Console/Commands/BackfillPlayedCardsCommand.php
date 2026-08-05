<?php

namespace App\Console\Commands;

use App\Modules\Game\Actions\RecordHistoricalPlayedCardsAction;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Queries\CountPlayedCardsForCoupleQuery;
use App\Modules\Memories\Queries\ListPlayedCardHistoryForCoupleQuery;
use App\Modules\Progress\Actions\CheckMilestonesAction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * One-off ops command: reconstruct the played set from the answers couples wrote
 * before it existed, then record the milestones that follows from it.
 *
 * Two phases, because P10 moved the progress map onto a counter that only started
 * being kept when P10 shipped. Cards played through a session were marked seen
 * from P3 on, but a card answered on the daily loop, or any card whose only trace
 * is the memory it produced, is missing from the set. Left alone, a couple with
 * history would watch their map fall — the milestone register is monotonic and
 * would keep what it holds, but the number above it would drop, which is exactly
 * the sort of thing a couple notices and we do not.
 *
 * Which loops count is decided HERE, in the app layer, next to the listener that
 * applies the same rule live: sessions and local play, never the daily card
 * (Piotr, P10 — the map counts the question game, the daily card has the streak).
 * Memories is asked for those origins and knows nothing about why.
 *
 * It supersedes progress:backfill-milestones from P8, which counted memories.
 * That command's second phase is this command's second phase; running the old one
 * after the switch would have re-derived the map from a counter nothing reads any
 * more.
 *
 * Idempotent twice over — the played set has a composite primary key and the
 * milestone register has one too, so both writes are ON CONFLICT DO NOTHING. Not
 * scheduled: this is a migration step, run by hand once.
 */
class BackfillPlayedCardsCommand extends Command
{
    /**
     * The loops the progress map counts. A memory from any other origin (today:
     * the daily card) stays out of the played set.
     *
     * @var list<string>
     */
    private const COUNTED_ORIGINS = ['session', 'local_game'];

    protected $signature = 'progress:backfill-played';

    protected $description = 'Rebuild the played-card set from older memories and record the milestones it earns.';

    public function handle(): int
    {
        $couplesChecked = 0;
        $cardsRecorded = 0;
        $milestonesRecorded = 0;

        // chunkById, not all(): the couples table must never be loaded whole into
        // memory — same rule as the weekly ritual cron.
        Couple::query()->chunkById(200, function (Collection $couples) use (
            &$couplesChecked,
            &$cardsRecorded,
            &$milestonesRecorded
        ): void {
            foreach ($couples as $couple) {
                $cardsRecorded += RecordHistoricalPlayedCardsAction::run(
                    $couple->id,
                    ListPlayedCardHistoryForCoupleQuery::run($couple->id, self::COUNTED_ORIGINS),
                );

                // Every couple is checked, including those with nothing played:
                // skipping them would quietly diverge from the listener, which
                // never makes that assumption either.
                $milestonesRecorded += CheckMilestonesAction::run(
                    $couple->id,
                    CountPlayedCardsForCoupleQuery::run($couple->id),
                );

                $couplesChecked++;
            }
        });

        $this->info("Checked {$couplesChecked} couples, recorded {$cardsRecorded} played cards and {$milestonesRecorded} milestones.");

        return self::SUCCESS;
    }
}
