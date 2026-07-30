<?php

namespace App\Modules\Rewards\Actions;

use App\Modules\Rewards\Data\AdRewardGrantedData;
use App\Modules\Rewards\Events\AdRewardGranted;
use App\Modules\Rewards\Models\AdRewardNonce;
use App\Modules\Rewards\Models\CoupleDailyAdReward;
use App\Modules\Rewards\Support\AdRewardOutcome;
use App\Modules\Rewards\Support\AdRewardPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pay for one rewarded ad whose SSV callback Google already vouched for. Replaces
 * the P6 client-trusted claim: the client can no longer grant itself credits, it
 * can only ask for a nonce and watch an ad (canon §5).
 *
 * The couple is read from the NONCE ROW, never from a parameter — that is what
 * makes the binding trustworthy. custom_data comes from the client, so the
 * signature proves the ad was real, and this lookup proves whose it was; passing
 * a couple id in alongside would reopen exactly the hole the nonce closes.
 *
 * Order inside the transaction is the replay guard: burn first, credit second.
 * Burning is a conditional UPDATE (whereNull consumed_at → affected == 1), so a
 * callback delivered twice — or two copies racing — pays exactly once. The daily
 * cap keeps its P6 shape (conditional increment, count < cap → affected == 1),
 * only the trigger moved from the client's request to the webhook.
 *
 * deckComplete arrives as a plain bool from the app layer: whether the couple has
 * anything left to unlock is Game's fact, and Rewards asking for it directly
 * would cost the module its leaf position (canon §8).
 */
final class GrantVerifiedAdRewardAction
{
    use AsAction;

    public function handle(string $nonce, CarbonImmutable $localDate, bool $deckComplete): AdRewardOutcome
    {
        $row = AdRewardNonce::query()->where('nonce', $nonce)->first();

        if ($row === null) {
            return AdRewardOutcome::NonceUnknown;
        }

        // Calendar day, never a timestamp comparison — the P4 daily-card lesson.
        $day = $localDate->toDateString();
        $coupleId = (int) $row->couple_id;

        // Outside the transaction, exactly as in P6: firstOrCreate is race-safe on
        // the unique (couple, day), and a concurrent insert must not abort the grant.
        if (! $deckComplete) {
            CoupleDailyAdReward::query()->firstOrCreate([
                'couple_id' => $coupleId,
                'reward_date' => $day,
            ]);
        }

        $outcome = DB::transaction(function () use ($row, $coupleId, $day, $deckComplete): AdRewardOutcome {
            $burned = AdRewardNonce::query()
                ->whereKey($row->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            if ($burned !== 1) {
                return AdRewardOutcome::NonceAlreadyUsed;
            }

            // A complete deck still burns the nonce: the view happened, it simply
            // buys nothing. Leaving it spendable would let a couple bank callbacks
            // and cash them in after the next content drop.
            if ($deckComplete) {
                return AdRewardOutcome::DeckComplete;
            }

            $claimed = CoupleDailyAdReward::query()
                ->where('couple_id', $coupleId)
                ->where('reward_date', $day)
                ->where('count', '<', AdRewardPolicy::DAILY_CAP)
                ->increment('count');

            if ($claimed !== 1) {
                return AdRewardOutcome::CapReached;
            }

            // In the same transaction as the increment, so a failed grant rolls the
            // counter back — no "counted but not credited" state.
            GrantCreditsAction::run($coupleId, AdRewardPolicy::CREDITS_PER_AD);

            return AdRewardOutcome::Granted;
        });

        // After the commit, never inside it (the P3 rule): a listener must not see
        // — or worse, be rolled back with — a grant that never landed.
        if ($outcome === AdRewardOutcome::Granted) {
            AdRewardGranted::dispatch(new AdRewardGrantedData(
                coupleId: $coupleId,
                userId: (int) $row->user_id,
                amount: AdRewardPolicy::CREDITS_PER_AD,
            ));
        }

        return $outcome;
    }
}
