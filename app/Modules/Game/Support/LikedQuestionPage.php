<?php

namespace App\Modules\Game\Support;

/**
 * One page of a couple's hearted cards: the question ids in like order plus the
 * cursors that walk the rest. Ids, not content — the list is assembled in Game,
 * the text comes from Catalog afterwards.
 *
 * A plain value object rather than a spatie Data: it is produced and consumed
 * inside Game (query → controller) and never crosses a module boundary, same as
 * DeckCard and RitualWeek.
 */
final readonly class LikedQuestionPage
{
    /**
     * @param  list<int>  $questionIds
     */
    public function __construct(
        public array $questionIds,
        public ?string $nextCursor,
        public ?string $prevCursor,
        public int $perPage,
    ) {}
}
