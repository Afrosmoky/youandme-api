<?php

namespace App\Modules\Rewards\Console\Commands;

use App\Modules\Rewards\Models\CoupleDailyAdReward;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Daily cron: drop ad-reward counter buckets older than the retention window.
 * Each day's cap is independent, so past days carry no meaning — pruning is what
 * keeps the "bucket, not log" choice bounded over time.
 *
 * One set-based DELETE, not a chunked loop: nothing is loaded into PHP memory
 * (which is what chunkById protects against — see the ritual cron, which iterates
 * couples and runs an action per row). Postgres has no DELETE ... LIMIT, so
 * chunking here would only add a subquery for no gain.
 *
 * The cutoff is computed in UTC. Safe for every timezone: the retention window is
 * a full week, dwarfing the ~26h spread of local dates around a UTC day.
 */
class PruneAdRewardCountersCommand extends Command
{
    /** Days of counters kept. A week is generous — nothing reads past days. */
    private const RETENTION_DAYS = 7;

    protected $signature = 'rewards:prune-ad-counters';

    protected $description = 'Delete rewarded-ad counter rows older than the retention window.';

    public function handle(): int
    {
        $cutoff = CarbonImmutable::now()->startOfDay()->subDays(self::RETENTION_DAYS)->toDateString();

        $deleted = CoupleDailyAdReward::query()->where('reward_date', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} ad-reward counter rows older than {$cutoff}.");

        return self::SUCCESS;
    }
}
