<?php

namespace App\Modules\Game\Support;

/**
 * What the closed deck looks like for one couple: how many cards are for sale,
 * how many this couple owns, and the per-card breakdown.
 *
 * `complete` requires a non-empty closed deck. "Nothing left to buy" is an honest
 * claim only when there was something to buy in the first place — an unseeded
 * deck (dev, tests, a fresh install) must not read as complete, because P7 slice
 * 2 uses this flag to switch OFF the repeatable ad earner.
 */
final readonly class DeckState
{
    /**
     * @param  list<DeckCard>  $cards
     */
    public function __construct(
        public int $lockedTotal,
        public int $unlockedCount,
        public array $cards,
    ) {}

    public function isComplete(): bool
    {
        return $this->lockedTotal > 0 && $this->unlockedCount >= $this->lockedTotal;
    }
}
