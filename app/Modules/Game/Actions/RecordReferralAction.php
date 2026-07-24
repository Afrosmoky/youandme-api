<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Models\Referral;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Record that one user referred another at registration. referrer_awarded_at
 * stays null — the referrer's bonus is paid later, on the referred user's first
 * app open (AwardPendingReferrerAction). Both ids are validated app-side before
 * this runs (referrer exists, is not the referred user).
 */
final class RecordReferralAction
{
    use AsAction;

    public function handle(int $referrerUserId, int $referredUserId): Referral
    {
        return Referral::create([
            'referrer_user_id' => $referrerUserId,
            'referred_user_id' => $referredUserId,
        ]);
    }
}
