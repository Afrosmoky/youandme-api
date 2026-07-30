<?php

namespace App\Modules\Rewards\Events;

use App\Modules\Rewards\Data\AdRewardGrantedData;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A couple was credited for a verified rewarded ad. The first event in this
 * project with a real consumer: with SSV the SERVER learns about the reward (the
 * callback arrives out of band), so the user has to be told by push.
 *
 * Rewards only announces it. It does not know that Notifications exist, let alone
 * call them — the wiring lives in the app composition root, exactly as with the
 * Auth mails in R1 Etap 3. That is what keeps both modules independent.
 */
final readonly class AdRewardGranted
{
    use Dispatchable;

    public function __construct(
        public AdRewardGrantedData $data,
    ) {}
}
