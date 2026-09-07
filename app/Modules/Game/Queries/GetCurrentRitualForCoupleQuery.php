<?php

namespace App\Modules\Game\Queries;

use App\Modules\Catalog\Queries\GetRitualByIdQuery;
use App\Modules\Game\Data\WeeklyRitualData;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Support\RitualWeek;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The couple's current weekly ritual: the assignment for THIS local week, with
 * content resolved via Catalog. Pure read — returns null when the couple has no
 * assignment for this week (the controller then assigns lazily and re-reads; a
 * Query never writes).
 *
 * The match is an equality on the week's Sunday, deliberately not "the newest
 * assignment on or before today". That older form was the beta failure: once a
 * couple had any row, this Query could never return null again, so the
 * controller's lazy-assignment branch was dead code and the whole feature ran on
 * the assumption that the Sunday cron was alive. It was not, and 28 couples sat
 * on ritual #1 for weeks, shown as "day 7 of 7" forever by the clamp.
 *
 * With the equality, a new local week always reads as null and heals itself on
 * the next request: the cron is now a convenience (it materialises the row before
 * anyone opens the app, so the client's Sunday push has something to show), not a
 * precondition. It also stops a couple west of UTC from seeing next week's ritual
 * a day early — the cron writes the row at 00:30 UTC Sunday, which is still
 * Saturday evening for them, and "<= today" would have handed it over immediately.
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
            ->where('started_on', RitualWeek::weekStart($localDate)->toDateString())
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
            completed: $assignment->completed_at !== null,
        );
    }
}
