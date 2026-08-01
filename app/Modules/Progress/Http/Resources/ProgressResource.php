<?php

namespace App\Modules\Progress\Http\Resources;

use App\Modules\Progress\Support\MilestoneState;
use App\Modules\Progress\Support\ProgressState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes the progress map. Wraps a ProgressState value object rather than a
 * model — Progress owns the shape even though the endpoint that returns it lives
 * in the app layer (which is the only place that can also supply the card count).
 * Same split as Game's DeckResource.
 *
 * Locked stages carry their name: the map is meant to show what lies ahead, and
 * the client dims what has not been reached.
 *
 * @mixin ProgressState
 */
class ProgressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProgressState $state */
        $state = $this->resource;

        return [
            'total_played' => $state->totalPlayed,
            // null once every stage is behind them — the map is finished.
            'next_threshold' => $state->nextThreshold(),
            'milestones' => array_map(fn (MilestoneState $milestone): array => [
                'slug' => $milestone->slug,
                'name' => $milestone->name,
                'threshold' => $milestone->threshold,
                'ordering' => $milestone->ordering,
                'unlocked' => $milestone->unlocked,
                'unlocked_at' => $milestone->unlockedAt?->toIso8601ZuluString(),
            ], $state->milestones),
        ];
    }
}
