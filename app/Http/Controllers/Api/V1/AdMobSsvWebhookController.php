<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Game\Models\Couple;
use App\Modules\Game\Queries\GetDeckStateForCoupleQuery;
use App\Modules\Rewards\Actions\GrantVerifiedAdRewardAction;
use App\Modules\Rewards\Actions\VerifyAdMobCallbackAction;
use App\Modules\Rewards\Exceptions\AdMobKeysUnavailableException;
use App\Modules\Rewards\Queries\FindAdRewardNonceQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Youandme\Auth\Models\User;

/**
 * GET /webhooks/admob-ssv — Google's server-side verification callback for a
 * watched rewarded ad, and from P7 the ONLY way an ad turns into credits.
 *
 * It lives in the app layer, not in Rewards, because granting depends on a Game
 * fact: a couple that already owns the whole closed deck must stop earning
 * (canon §8). Rewards asking Game directly would cost it its leaf position, so
 * the orchestrator asks and passes the answer down as a bool — the same "raise
 * the wiring one level" move as registration in R1 (guide 4.1).
 *
 * Unauthenticated by nature (Google holds no token), so trust comes from two
 * independent checks: Google's signature proves the ad was really served, and our
 * own nonce proves which couple it was authorized for and that it has not been
 * cashed already. Neither replaces the other (canon §7, decision 3).
 *
 * Status codes carry meaning to Google, so they are split along "is this verdict
 * final?": every rejection decided here (bad signature, unknown or spent nonce,
 * complete deck) answers 200, because a redelivery would reach the same verdict.
 * The one exception is our own outage — Google's keys unreachable — which answers
 * 503 so the callback comes back and a genuine reward is not lost to a network
 * blip. What the callback claims about the reward (reward_amount, reward_item) is
 * ignored: the server decides what an ad is worth. So is user_id, which the
 * client sets; the nonce is the binding.
 */
final class AdMobSsvWebhookController
{
    public function handle(Request $request): Response
    {
        $rawQuery = (string) $request->server('QUERY_STRING');
        $signature = (string) $request->query('signature', '');
        $keyId = (string) $request->query('key_id', '');
        $nonce = (string) $request->query('custom_data', '');

        // Google signs the query string up to (excluding) "&signature=", so the
        // payload has to be cut from the RAW string — re-encoding the parsed
        // parameters would reorder or re-escape them and break the signature.
        $signatureAt = strpos($rawQuery, '&signature=');

        if ($signatureAt === false || $signature === '' || $keyId === '') {
            return $this->reject('malformed callback', ['query' => $rawQuery]);
        }

        try {
            $genuine = VerifyAdMobCallbackAction::run(substr($rawQuery, 0, $signatureAt), $signature, $keyId);
        } catch (AdMobKeysUnavailableException $exception) {
            // Undecidable, not invalid: ask Google to send it again rather than
            // burning a real reward on our outage.
            Log::warning('AdMob SSV callback deferred', [
                'reason' => 'verifier keys unavailable',
                'error' => $exception->getMessage(),
            ]);

            return response('', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! $genuine) {
            return $this->reject('bad signature', ['key_id' => $keyId]);
        }

        $nonceInfo = FindAdRewardNonceQuery::run($nonce);

        if ($nonceInfo === null) {
            return $this->reject('unknown nonce', []);
        }

        $couple = Couple::find($nonceInfo->coupleId);

        if ($couple === null) {
            return $this->reject('nonce points at a missing couple', ['couple_id' => $nonceInfo->coupleId]);
        }

        // The cap counts in the couple's local day (users.timezone), resolved here
        // and passed down as a plain date — the same split as the daily card. A
        // deleted user falls back to app time rather than blocking the grant.
        /** @var User|null $adWatcher */
        $adWatcher = User::find($nonceInfo->userId);
        $timezone = is_string($adWatcher?->timezone) ? $adWatcher->timezone : (string) config('app.timezone');

        $outcome = GrantVerifiedAdRewardAction::run(
            $nonce,
            CarbonImmutable::now($timezone)->startOfDay(),
            GetDeckStateForCoupleQuery::run($couple)->isComplete(),
        );

        Log::info('AdMob SSV callback processed', [
            'outcome' => $outcome->name,
            'couple_id' => $nonceInfo->coupleId,
            'transaction_id' => $request->query('transaction_id'),
        ]);

        return $this->ok();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function reject(string $reason, array $context): Response
    {
        Log::warning('AdMob SSV callback rejected', ['reason' => $reason] + $context);

        return $this->ok();
    }

    /**
     * 200 with an empty body — what Google expects, and what stops it retrying a
     * callback we have already judged (see the class docblock).
     */
    private function ok(): Response
    {
        return response('', Response::HTTP_OK);
    }
}
