<?php

namespace App\Modules\Rewards\Actions;

use App\Modules\Rewards\Models\AdRewardNonce;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Hand out a one-time token for one rewarded-ad view. The couple and user come
 * from the caller's token (resolved in the controller), never from the request
 * body — that server-side binding is the entire point: the client carries the
 * nonce into the ad, but cannot choose whose it is.
 *
 * A fresh nonce per ad, deliberately: reusing one would make the callback
 * replayable again, which is exactly what the nonce exists to prevent. Unspent
 * nonces are cheap and pruned weekly.
 */
final class IssueAdRewardNonceAction
{
    /** 32 alphanumeric chars: ~190 bits, and safe to carry in a query string. */
    private const NONCE_LENGTH = 32;

    use AsAction;

    public function handle(int $coupleId, int $userId): string
    {
        $nonce = Str::random(self::NONCE_LENGTH);

        AdRewardNonce::query()->create([
            'couple_id' => $coupleId,
            'user_id' => $userId,
            'nonce' => $nonce,
            'issued_at' => now(),
        ]);

        return $nonce;
    }
}
