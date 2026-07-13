<?php

namespace App\Modules\Game\Http\Controllers;

use App\Modules\Game\Actions\AssignWeeklyRitualAction;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Queries\GetCurrentRitualForCoupleQuery;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GET /weekly-ritual — a Game aggregate (the couple's current ritual assignment)
 * with the content pulled downstream from Catalog. Not peer-combining, so it stays
 * in Game.
 *
 * Cold start: a couple with no assignment (fresh registration mid-week) is
 * assigned lazily from this week's Sunday, so it does not have to wait until the
 * next cron. The write goes through the Action (Query stays read-only): read →
 * null? → assign → read.
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
            AssignWeeklyRitualAction::run($couple, $localDate->startOfWeek(CarbonInterface::SUNDAY));
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
