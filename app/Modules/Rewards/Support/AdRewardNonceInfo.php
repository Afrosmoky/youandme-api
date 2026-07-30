<?php

namespace App\Modules\Rewards\Support;

/**
 * Who a nonce belongs to, for the webhook orchestrator: it needs the couple (to
 * ask Game whether the deck is already complete) and the user (whose timezone
 * defines the local day the cap counts in) BEFORE it can call the grant.
 *
 * A plain value object, like AdRewardNonceInfo's neighbours in Support — produced
 * by a Rewards query, consumed by the app layer, never serialized.
 *
 * It deliberately carries no authority: the grant re-reads the nonce inside
 * Rewards and credits the couple recorded there, so a mistaken (or tampered)
 * couple id in the orchestrator cannot redirect a credit.
 */
final readonly class AdRewardNonceInfo
{
    public function __construct(
        public int $coupleId,
        public int $userId,
        public bool $consumed,
    ) {}
}
