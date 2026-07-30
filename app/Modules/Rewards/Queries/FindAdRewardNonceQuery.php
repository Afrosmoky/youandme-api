<?php

namespace App\Modules\Rewards\Queries;

use App\Modules\Rewards\Models\AdRewardNonce;
use App\Modules\Rewards\Support\AdRewardNonceInfo;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Who does this nonce belong to? Read-only (CQS) — burning it is the grant
 * Action's job. The webhook orchestrator needs the answer first, to ask Game
 * about the deck and Auth about the timezone before it can grant anything.
 *
 * Returns null for an unknown token; a spent one is returned with consumed=true
 * so the caller can log a replay as such instead of as a forgery.
 */
final class FindAdRewardNonceQuery
{
    use AsAction;

    public function handle(string $nonce): ?AdRewardNonceInfo
    {
        $row = AdRewardNonce::query()->where('nonce', $nonce)->first();

        if ($row === null) {
            return null;
        }

        return new AdRewardNonceInfo(
            coupleId: (int) $row->couple_id,
            userId: (int) $row->user_id,
            consumed: $row->consumed_at !== null,
        );
    }
}
