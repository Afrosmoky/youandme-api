<?php

namespace App\Modules\Progress\Support;

use Carbon\CarbonInterface;

/**
 * One node of the progress map as this couple sees it: the stage itself plus
 * whether they have reached it.
 *
 * The name is carried whether or not the milestone is unlocked — the map exists
 * to show a couple where they are going, so a locked node is dimmed by the
 * client, not hidden by the server.
 *
 * A plain value object, not a spatie Data: it is produced by a Progress query and
 * rendered by a Progress resource, and never crosses a module boundary — same as
 * Game's DeckCard.
 */
final readonly class MilestoneState
{
    public function __construct(
        public string $slug,
        public string $name,
        public int $threshold,
        public int $ordering,
        public bool $unlocked,
        public ?CarbonInterface $unlockedAt,
    ) {}
}
