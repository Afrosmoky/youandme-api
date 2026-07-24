<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\Referral;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pay the referrer's half of the symmetric +5 bonus, triggered by the referred
 * user's first app open. Finds the unpaid referral for this referred user,
 * resolves the referrer's couple (via couples.user_a_id — the auto-couple
 * invariant, so Game never reads users.active_couple_id from Auth), grants +5,
 * and stamps referrer_awarded_at.
 *
 * Idempotent and race-safe: the stamp is an atomic conditional UPDATE (whereNull);
 * only the caller that flips it (affected == 1) grants the cards. A second open
 * (or a concurrent request) finds it already set → no-op. No referral / already
 * paid → no-op.
 */
final class AwardPendingReferrerAction
{
    /** Symmetric referral bonus (canon §1 rows 7–8): the referrer's deferred half. */
    private const REFERRAL_BONUS = 5;

    use AsAction;

    public function handle(int $referredUserId): void
    {
        $referral = Referral::query()
            ->where('referred_user_id', $referredUserId)
            ->whereNull('referrer_awarded_at')
            ->first();

        if ($referral === null) {
            return;
        }

        $couple = Couple::query()->where('user_a_id', $referral->referrer_user_id)->first();
        if ($couple === null) {
            return;
        }

        // Atomic claim: only the update that actually flips referrer_awarded_at
        // proceeds to grant, so a concurrent first-open cannot double-pay.
        $claimed = Referral::query()
            ->whereKey($referral->id)
            ->whereNull('referrer_awarded_at')
            ->update(['referrer_awarded_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        GrantCardsAction::run($couple, self::REFERRAL_BONUS);
    }
}
