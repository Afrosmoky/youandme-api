<?php

namespace App\Modules\Progress\Support;

/**
 * The whole progress map for one couple: how many cards they have played and
 * where that puts them along the route.
 */
final readonly class ProgressState
{
    /**
     * @param  list<MilestoneState>  $milestones  in map order
     */
    public function __construct(
        public int $totalPlayed,
        public array $milestones,
    ) {}

    /**
     * The count the couple is playing towards, or null once nothing is left.
     *
     * Defined as the lowest threshold ABOVE the current count, not "the lowest
     * one not yet recorded". The two normally agree, but they part ways right
     * after a milestone is added below a couple's count: until the next card
     * triggers the check, that stage is unrecorded, and pointing the map
     * backwards at it would read as a target still to reach — when in truth they
     * have already passed it. The client renders this as "N cards to go", so it
     * must always be ahead.
     *
     * Computed as a minimum rather than "the first one in the list", so a seed
     * whose ordering disagrees with its thresholds cannot produce a nonsense
     * target.
     */
    public function nextThreshold(): ?int
    {
        $ahead = array_filter(
            array_map(fn (MilestoneState $milestone): int => $milestone->threshold, $this->milestones),
            fn (int $threshold): bool => $threshold > $this->totalPlayed,
        );

        return $ahead === [] ? null : min($ahead);
    }
}
