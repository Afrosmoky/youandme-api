<?php

namespace App\Modules\Game\Http\Controllers;

use App\Modules\Game\Actions\AssignWeeklyRitualAction;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Queries\GetCurrentRitualForCoupleQuery;
use App\Modules\Game\Support\RitualWeek;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GET /weekly-ritual — a Game aggregate (the couple's current ritual assignment)
 * with the content pulled downstream from Catalog. Not peer-combining, so it stays
 * in Game.
 *
 * Cold start AND every new week: the read matches this local week's Sunday
 * exactly, so a couple with no row for it — a fresh registration mid-week, or
 * anyone whose local Sunday has opened since the last cron run — is assigned
 * lazily here. The write goes through the Action (Query stays read-only): read →
 * null? → assign → read. This is what makes the feature heal itself when the
 * schedule stops; the cron only gets there first.
 *
 * The couple's local date is resolved here from users.timezone and passed down, so
 * the Query/Action stay timezone-agnostic.
 */
final class WeeklyRitualController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $couple = Couple::findOrFail($user->active_couple_id);
        $localDate = CarbonImmutable::now($user->timezone)->startOfDay();

        $ritual = GetCurrentRitualForCoupleQuery::run($couple, $localDate);

        if ($ritual === null) {
            AssignWeeklyRitualAction::run($couple, RitualWeek::weekStart($localDate));
            $ritual = GetCurrentRitualForCoupleQuery::run($couple, $localDate);
        }

        if ($ritual === null) {
            // Only reachable if no rituals are seeded at all.
            abort(Response::HTTP_NOT_FOUND, 'Brak rytuału tygodnia.');
        }

        return response()->json([
            'ritual' => [
                'ulid' => $ritual->ritual->ulid,
                'title' => $ritual->ritual->title,
                'body' => $ritual->ritual->body,
            ],
            'started_on' => $ritual->startedOn,
            'day_of_week' => $ritual->dayOfWeek,
        ]);
    }
}
