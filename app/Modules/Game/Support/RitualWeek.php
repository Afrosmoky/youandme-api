<?php

namespace App\Modules\Game\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The "day X of 7" counter — a pure calendar computation, not a stored column.
 * Days elapsed since started_on, plus one, clamped to 1..7.
 */
final class RitualWeek
{
    public static function dayOfWeek(CarbonInterface $startedOn, CarbonInterface $localDate): int
    {
        // Compare CALENDAR days, not instants: started_on is stored in UTC while
        // localDate carries the couple's timezone, so a raw diff would count the
        // offset as hours. Normalising both to their date string re-anchors them to
        // the same clock (same lesson as the daily card).
        $start = CarbonImmutable::parse($startedOn->toDateString());
        $today = CarbonImmutable::parse($localDate->toDateString());

        $elapsed = (int) $start->diffInDays($today, false);

        return max(1, min(7, $elapsed + 1));
    }
}
