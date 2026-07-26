<?php

namespace App\Modules\Rewards\Actions;

use App\Modules\Rewards\Models\CoupleReward;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Add credits to a couple's reward account — the single write path for every
 * bonus (referral, share; ads/rating in P6, purchases in P7). Takes the couple id
 * as a plain value, so Rewards stays a leaf (it never loads Game's Couple).
 *
 * The account row is created lazily on the first grant (firstOrCreate → race-safe
 * createOrFirst on the unique couple_id), then incremented atomically in SQL
 * (credits = credits + amount), so concurrent grants do not lose updates.
 */
final class GrantCreditsAction
{
    use AsAction;

    public function handle(int $coupleId, int $amount): void
    {
        CoupleReward::query()->firstOrCreate(['couple_id' => $coupleId]);

        CoupleReward::query()->where('couple_id', $coupleId)->increment('credits', $amount);
    }
}
