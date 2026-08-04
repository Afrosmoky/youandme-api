<?php

namespace App\Modules\Memories\Queries;

use App\Modules\Memories\Data\MemoryData;
use App\Modules\Memories\Models\Memory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The couple's most recent memory answered inside any of the given [from, to)
 * ranges, or null.
 *
 * Deliberately says nothing about anniversaries: it takes ranges, not a rule, so
 * the calendar policy (what a month or a year back means, month-end clamping)
 * stays in the caller — the same discipline as the daily card, where the Action
 * receives a date and never a timezone.
 *
 * THIRD intention on `memories`, and it reads like the list, not like the counter:
 * soft-deleted rows are out. A couple that removed a memory has said "stop showing
 * it to us", and resurfacing it in a push would be the loudest possible way to
 * ignore that. Only CountMemoriesForCoupleQuery says withTrashed, because a
 * lifetime count must not fall.
 */
final class FindLatestMemoryAnsweredWithinQuery
{
    use AsAction;

    /**
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $windows
     */
    public function handle(int $coupleId, array $windows): ?MemoryData
    {
        if ($windows === []) {
            return null;
        }

        $memory = Memory::query()
            ->where('couple_id', $coupleId)
            ->where(function (Builder $query) use ($windows): void {
                foreach ($windows as [$from, $to]) {
                    // Half-open ranges: a memory answered exactly at midnight
                    // belongs to the day that starts, never to both.
                    $query->orWhere(fn (Builder $window) => $window
                        ->where('answered_at', '>=', $from)
                        ->where('answered_at', '<', $to));
                }
            })
            ->with('question.category')
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->first();

        return $memory ? MemoryData::fromModel($memory) : null;
    }
}
