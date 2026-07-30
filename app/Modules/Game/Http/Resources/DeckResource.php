<?php

namespace App\Modules\Game\Http\Resources;

use App\Modules\Game\Support\DeckCard;
use App\Modules\Game\Support\DeckState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes the closed deck for one couple. Wraps a DeckState value object
 * rather than a model — Game owns the shape, and both readers use it: GET /deck
 * and the unlock endpoint in the app layer (which answers with the deck the
 * couple now has, so the client needs no second round trip).
 *
 * Cards carry no body — see DeckCard.
 *
 * @mixin DeckState
 */
class DeckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DeckState $state */
        $state = $this->resource;

        return [
            'locked_total' => $state->lockedTotal,
            'unlocked_count' => $state->unlockedCount,
            'complete' => $state->isComplete(),
            'cards' => array_map(fn (DeckCard $card): array => [
                'ulid' => $card->ulid,
                'category' => $card->categorySlug !== null ? [
                    'slug' => $card->categorySlug,
                    'name' => $card->categoryName,
                ] : null,
                'unlocked' => $card->unlocked,
            ], $state->cards),
        ];
    }
}
