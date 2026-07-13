<?php

namespace App\Modules\Game\Queries;

use App\Modules\Catalog\Queries\GetRitualByIdQuery;
use App\Modules\Game\Data\WeeklyRitualData;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Support\RitualWeek;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The couple's current weekly ritual: the most recent assignment on or before the
 * couple's local date, with content resolved via Catalog. Pure read — returns null
 * when the couple has no assignment yet (the controller then assigns lazily and
 * re-reads; a Query never writes).
 *
 * The local date is a parameter (computed in the controller from users.timezone),
 * keeping this couple/Game code out of Auth internals.
 */
final class GetCurrentRitualForCoupleQuery
{
    use AsAction;

    public function handle(Couple $couple, CarbonImmutable $localDate): ?WeeklyRitualData
    {
        $assignment = $couple->weeklyRituals()
            ->where('started_on', '<=', $localDate->toDateString())
            ->orderByDesc('started_on')
            ->orderByDesc('id')
            ->first();

        if ($assignment === null) {
            return null;
        }

        $ritual = GetRitualByIdQuery::run($assignment->ritual_id);
        if ($ritual === null) {
            return null;
        }

        return new WeeklyRitualData(
            ritual: $ritual,
            startedOn: $assignment->started_on->toDateString(),
            dayOfWeek: RitualWeek::dayOfWeek($assignment->started_on, $localDate),
        );
    }
}
