<?php

namespace App\Modules\Rewards\Support;

/**
 * What happened to one verified SSV callback. Every outcome is a normal, expected
 * end state — the webhook always answers 200 to Google, and this is what gets
 * logged (and asserted in tests) instead.
 *
 * Note that four of the five outcomes burn the nonce anyway: a token is spent by
 * being presented, whether or not it produced a credit. Only an unknown token
 * leaves nothing behind, because there was nothing to burn.
 */
enum AdRewardOutcome
{
    /** Credited. */
    case Granted;

    /** No such token — a forged or long-pruned custom_data. */
    case NonceUnknown;

    /** The token was already spent: a replayed callback (or a duplicate delivery). */
    case NonceAlreadyUsed;

    /** The couple already owns the whole closed deck — nothing left to buy (canon §8). */
    case DeckComplete;

    /** The couple already took its five ad credits for the local day. */
    case CapReached;
}
