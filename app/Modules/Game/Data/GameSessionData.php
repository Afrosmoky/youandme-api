<?php

namespace App\Modules\Game\Data;

use App\Modules\Catalog\Data\CategoryData;
use App\Modules\Game\Models\GameSession;
use Spatie\LaravelData\Data;

/**
 * Public contract for a game session. HTTP responses serialize via
 * SessionResource (byte-identical); this DTO is the typed read API for other
 * modules. remaining_ids stays internal (not exposed) — only remainingCount.
 */
final class GameSessionData extends Data
{
    public function __construct(
        public readonly string $ulid,
        public readonly string $mode,
        public readonly ?CategoryData $category,
        public readonly string $startedAt,
        public readonly ?string $endedAt,
        public readonly int $currentIndex,
        public readonly int $remainingCount,
        public readonly int $cardsDrawnCount,
        public readonly int $cardsSavedCount,
    ) {}

    public static function fromModel(GameSession $session): self
    {
        $state = $session->state;
        $remainingIds = is_array($state['remaining_ids'] ?? null) ? $state['remaining_ids'] : [];
        $currentIndex = isset($state['current_index']) ? (int) $state['current_index'] : 0;

        return new self(
            ulid: $session->ulid,
            mode: $session->mode,
            category: $session->category ? CategoryData::fromModel($session->category) : null,
            startedAt: $session->started_at->toIso8601ZuluString(),
            endedAt: $session->ended_at?->toIso8601ZuluString(),
            currentIndex: $currentIndex,
            remainingCount: count($remainingIds),
            cardsDrawnCount: (int) $session->cards_drawn_count,
            cardsSavedCount: (int) $session->cards_saved_count,
        );
    }
}
