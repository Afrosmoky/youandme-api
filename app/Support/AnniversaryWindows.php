<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Which slices of the past have their anniversary on a given local day.
 *
 * The calendar rule lives in the app layer, not in Memories: Memories only knows
 * how to hand back memories answered inside a time range, and what counts as an
 * anniversary is push policy. It is expressed as [from, to) instants in UTC
 * because that is the only form a `timestamptz` comparison can trust — a Carbon
 * instance bound in a local timezone would be formatted without its offset and
 * silently read as UTC, which is the timezone lesson from P4 in its database form.
 *
 * Two rules worth stating out loud:
 *
 * - No overflow. "A month before 31 March" is 28 February, not 3 March.
 * - Clamping on the last day of a month. February can never name a 30th, so on the
 *   last day of a month the window is stretched to the end of the candidate month:
 *   28 February swallows 28–31 January, and a memory made on 29 February is
 *   celebrated on 28 February in ordinary years. Without it, memories made on the
 *   29th–31st would quietly lose their anniversary in the short months.
 */
final readonly class AnniversaryWindows
{
    /**
     * How far back yearly anniversaries reach. Ten years is far beyond anything
     * this app can have on disk; the bound exists so a single query can carry all
     * the candidate ranges instead of growing without limit.
     */
    public const MAX_YEARS = 10;

    /**
     * Every yearly anniversary falling on this local day, newest first.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public static function years(CarbonImmutable $localToday, int $maxYears = self::MAX_YEARS): array
    {
        $windows = [];

        for ($years = 1; $years <= $maxYears; $years++) {
            $windows[] = self::windowFor($localToday, $localToday->subYearsNoOverflow($years));
        }

        return $windows;
    }

    /**
     * The one-month anniversary falling on this local day.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function month(CarbonImmutable $localToday): array
    {
        return self::windowFor($localToday, $localToday->subMonthNoOverflow());
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function windowFor(CarbonImmutable $localToday, CarbonImmutable $candidate): array
    {
        $from = $candidate->startOfDay();

        $to = $localToday->isLastOfMonth()
            ? $candidate->endOfMonth()->startOfDay()->addDay()
            : $from->addDay();

        return [$from->setTimezone('UTC'), $to->setTimezone('UTC')];
    }
}
