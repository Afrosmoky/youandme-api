<?php

namespace App\Modules\Memories\Queries;

use App\Modules\Memories\Models\Memory;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * When this couple first answered each question, for the origins the caller asks
 * about. Public API of Memories, read by the P10 backfill: the played set was
 * introduced after these answers were written, so their history has to be
 * reconstructed from the only place that kept it.
 *
 * The origins arrive as a parameter rather than being decided here. Which loops
 * the progress map counts is a product rule (sessions and local play, not the
 * daily card), and it belongs with the rest of the map wiring in the app layer —
 * Memories only knows that `origin` is one of its columns.
 *
 * Earliest answer per question, because that is when the card was played; a
 * question answered twice was still one card. withTrashed, because deleting a
 * memory does not un-play it — the same lifetime reading the counter used before
 * it moved to Game.
 *
 * FOURTH intention on `memories`, and it reads like the counter, not like the
 * list: soft-deleted rows are in.
 *
 * @return array<int, CarbonImmutable> question id => first answered_at
 */
final class ListPlayedCardHistoryForCoupleQuery
{
    use AsAction;

    /**
     * @param  list<string>  $origins
     * @return array<int, CarbonImmutable>
     */
    public function handle(int $coupleId, array $origins): array
    {
        if ($origins === []) {
            return [];
        }

        return Memory::query()
            ->withTrashed()
            ->where('couple_id', $coupleId)
            ->whereIn('origin', $origins)
            ->selectRaw('question_id, MIN(answered_at) as first_answered_at')
            ->groupBy('question_id')
            ->pluck('first_answered_at', 'question_id')
            ->map(fn (string $answeredAt): CarbonImmutable => CarbonImmutable::parse($answeredAt))
            ->all();
    }
}
