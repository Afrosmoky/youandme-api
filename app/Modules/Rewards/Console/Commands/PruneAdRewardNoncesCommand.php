<?php

namespace App\Modules\Rewards\Console\Commands;

use App\Modules\Rewards\Models\AdRewardNonce;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Daily cron: drop ad-reward nonces older than the retention window — spent ones
 * (their job is done) and stale unspent ones alike.
 *
 * A nonce is issued seconds before its callback arrives, so a week is absurdly
 * generous on purpose: it costs a tiny table and removes any chance of pruning a
 * token whose callback is still in flight. Pruned unspent nonces simply stop
 * working, which is the correct outcome for a view that never happened.
 *
 * Set-based DELETE for the same reason as the counter prune: nothing needs to be
 * loaded into PHP.
 */
class PruneAdRewardNoncesCommand extends Command
{
    /** Days of nonces kept. */
    private const RETENTION_DAYS = 7;

    protected $signature = 'rewards:prune-ad-nonces';

    protected $description = 'Delete rewarded-ad nonces older than the retention window.';

    public function handle(): int
    {
        $cutoff = CarbonImmutable::now()->subDays(self::RETENTION_DAYS);

        $deleted = AdRewardNonce::query()->where('issued_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} ad-reward nonces issued before {$cutoff->toDateTimeString()}.");

        return self::SUCCESS;
    }
}
