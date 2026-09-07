<?php

namespace App\Modules\Game\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calendar arithmetic for the weekly ritual — pure computation, no stored column.
 *
 * Both helpers normalise their inputs to a date string first. started_on is a
 * date stored in UTC while the local date carries the couple's timezone, so any
 * raw instant arithmetic would count the offset as hours (the lesson from the
 * daily card). Re-anchoring to the calendar is what makes the two comparable.
 */
final class RitualWeek
{
    /**
     * The Sunday that opens the couple's current local week — the identity of a
     * ritual assignment. One definition, used by the read, the lazy assignment
     * and the completion write alike: they MUST agree on which row is "current",
     * or a couple could complete a row it is not being shown.
     */
    public static function weekStart(CarbonInterface $localDate): CarbonImmutable
    {
        return CarbonImmutable::parse($localDate->toDateString())
            ->startOfWeek(CarbonInterface::SUNDAY);
    }

    /**
     * The "day X of 7" counter: days elapsed since started_on, plus one.
     *
     * The 1..7 clamp is a safety belt, not the load-bearing part. It used to hide
     * the stale-assignment bug (a month-old row read as "day 7 of 7" forever);
     * since the read was narrowed to the current week, an assignment is at most
     * six days old and the clamp is true by construction.
     */
    public static function dayOfWeek(CarbonInterface $startedOn, CarbonInterface $localDate): int
    {
        $start = CarbonImmutable::parse($startedOn->toDateString());
        $today = CarbonImmutable::parse($localDate->toDateString());

        $elapsed = (int) $start->diffInDays($today, false);

        return max(1, min(7, $elapsed + 1));
    }
}
