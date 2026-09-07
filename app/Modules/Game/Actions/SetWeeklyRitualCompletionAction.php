<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\CoupleWeeklyRitual;
use App\Modules\Game\Support\RitualWeek;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mark the couple's current weekly ritual done, or take that back. One Action for
 * both directions, because the caller states the target state rather than asking
 * for a flip — the two REST verbs are then idempotent, and a retried request
 * cannot silently undo what the first one did (the P5 lesson, as applied to
 * memory favourites in P9).
 *
 * Idempotent in the direction that matters: completing an already-completed ritual
 * keeps the FIRST instant. The marker answers "when did they confirm", and a
 * double tap must not rewrite that answer.
 *
 * Only the current week can be marked, and it is found by the same equality the
 * read uses (RitualWeek::weekStart) — so the couple can only ever complete the
 * ritual it is actually being shown. The couple comes from the token and there is
 * no identifier in the request at all, which is what closes both the IDOR and the
 * "let me complete a week from last month" path in one move. Nothing to spoof.
 *
 * No assignment for this week → 404. Completing must not create one: "we did it"
 * cannot be what hands a couple its ritual. The client re-reads GET /weekly-ritual
 * instead — see the controller for that contract.
 *
 * Deliberately silent: no domain event. Stage II owns the consequences of
 * completing (reward, weekly streak), and Game already carries four dispatched
 * events nobody listens to; a fifth would be more of that debt, not a seam.
 */
final class SetWeeklyRitualCompletionAction
{
    use AsAction;

    public function handle(Couple $couple, CarbonImmutable $localDate, bool $completed): CoupleWeeklyRitual
    {
        $assignment = $couple->weeklyRituals()
            ->where('started_on', RitualWeek::weekStart($localDate)->toDateString())
            ->first();

        if ($assignment === null) {
            abort(Response::HTTP_NOT_FOUND, 'Brak rytuału tygodnia.');
        }

        if ($completed && $assignment->completed_at === null) {
            $assignment->completed_at = now();
            $assignment->save();
        }

        if (! $completed && $assignment->completed_at !== null) {
            $assignment->completed_at = null;
            $assignment->save();
        }

        return $assignment;
    }
}
