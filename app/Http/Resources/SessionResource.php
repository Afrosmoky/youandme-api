<?php

namespace App\Http\Resources;

use App\Models\GameSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GameSession
 */
class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->state;
        $remainingIds = is_array($state['remaining_ids'] ?? null) ? $state['remaining_ids'] : [];
        $currentIndex = isset($state['current_index']) ? (int) $state['current_index'] : 0;

        return [
            'ulid' => $this->ulid,
            'mode' => $this->mode,
            // remaining_ids is deliberately not exposed — internal server state.
            'category' => $this->category ? [
                'slug' => $this->category->slug,
                'name' => $this->category->name,
            ] : null,
            'started_at' => $this->started_at->toIso8601ZuluString(),
            'ended_at' => $this->ended_at?->toIso8601ZuluString(),
            'current_index' => $currentIndex,
            'remaining_count' => count($remainingIds),
            'cards_drawn_count' => $this->cards_drawn_count,
            'cards_saved_count' => $this->cards_saved_count,
        ];
    }
}
