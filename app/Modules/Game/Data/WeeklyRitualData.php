<?php

namespace App\Modules\Game\Data;

use App\Modules\Catalog\Data\RitualData;
use Spatie\LaravelData\Data;

/**
 * Read contract for GET /weekly-ritual: the couple's current ritual (content
 * pulled downstream from Catalog as RitualData) plus when it started, which day
 * of the 7 the couple is on, and whether they have marked it done. dayOfWeek is
 * computed, not stored.
 *
 * completed is a boolean, not the instant behind it — the client only needs to
 * know whether the button is pressed, exactly as GET /daily-card exposes
 * answered_today rather than an answered_at. The timestamp stays in the database,
 * where the statistics will be read from.
 */
final class WeeklyRitualData extends Data
{
    public function __construct(
        public readonly RitualData $ritual,
        public readonly string $startedOn,
        public readonly int $dayOfWeek,
        public readonly bool $completed,
    ) {}
}
