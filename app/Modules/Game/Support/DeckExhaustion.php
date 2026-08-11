<?php

namespace App\Modules\Game\Support;

/**
 * Why this couple's deck request came back with nothing, plus how big the unlock
 * funnel is behind it.
 *
 * lockedRemaining rides along on every reason, not just LockedAvailable: the
 * client may want to mention the closed deck on the "try another category" screen
 * too, and one shape is cheaper to read than a field that appears conditionally.
 */
final readonly class DeckExhaustion
{
    public function __construct(
        public ExhaustionReason $reason,
        public int $lockedRemaining,
    ) {}
}
