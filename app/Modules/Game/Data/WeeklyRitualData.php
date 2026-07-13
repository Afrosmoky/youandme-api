<?php

namespace App\Modules\Game\Data;

use App\Modules\Catalog\Data\RitualData;
use Spatie\LaravelData\Data;

/**
 * Read contract for GET /weekly-ritual: the couple's current ritual (content
 * pulled downstream from Catalog as RitualData) plus when it started and which
 * day of the 7 the couple is on. dayOfWeek is computed, not stored.
 */
final class WeeklyRitualData extends Data
{
    public function __construct(
        public readonly RitualData $ritual,
        public readonly string $startedOn,
        public readonly int $dayOfWeek,
    ) {}
}
