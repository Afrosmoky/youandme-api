<?php

namespace App\Modules\Game\Support;

/**
 * One card of the closed deck as the deck screen sees it: an address (ulid), a
 * hint of what it is (category) and whether this couple already owns it.
 *
 * Deliberately WITHOUT the body — a locked card must not leak its content before
 * it is paid for, and the client has no use for it: the text arrives through
 * gameplay (/questions/next), not through the shop.
 *
 * A plain value object, not a spatie Data: it is produced and consumed inside
 * Game (query → controller) and never crosses a module boundary — same as
 * DailyCard / RitualWeek.
 */
final readonly class DeckCard
{
    public function __construct(
        public string $ulid,
        public ?string $categorySlug,
        public ?string $categoryName,
        public bool $unlocked,
    ) {}
}
