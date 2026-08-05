<?php

namespace App\Listeners;

use App\Modules\Game\Events\CardsPlayed;
use App\Modules\Game\Queries\CountPlayedCardsForCoupleQuery;
use App\Modules\Progress\Actions\CheckMilestonesAction;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Composition-root wiring: playing cards advances the progress map.
 *
 * It replaces the P8 listener on MemoryCreated, and the reason is a change of
 * definition rather than of plumbing. The map is meant to count PLAYED cards
 * (Zakres §3.7); P8 could only count SAVED ones, because "a card was played" was
 * not yet a fact anything recorded. P10 makes it one — the local game plays cards
 * that are never written down, and a skipped card in a session was always played
 * without being saved. So the counter moves to where that fact now lives, and the
 * old path is removed rather than kept alongside: two sources feeding one register
 * is the CQS smell P8 was careful to avoid (canon ◆C, option C2 rejected).
 *
 * What changed for the couple: a skipped card now counts, a locally played card
 * counts, and the daily card no longer does — that loop has its own system, the
 * streak (Piotr, P10). Saving an answer still advances the map, because the save
 * marks the card played on its way through Game.
 *
 * Event rather than command, as in P8: the caller needs nothing back. Playing a
 * card neither waits for the milestone check nor cares how it went.
 *
 * The listener is the only place that knows both modules: Game counts, Progress
 * decides. Progress receives the total as a value and stays a leaf.
 *
 * Nothing here may break the play. The event fires after commit, so a throw would
 * not roll anything back, but it would still surface as a failed request for cards
 * the couple did play. Swallowing is safe because CheckMilestonesAction records
 * everything crossed rather than just the newest step — a lost run is repaired by
 * the next card.
 */
final class CheckMilestonesOnCardsPlayed
{
    public function handle(CardsPlayed $event): void
    {
        $coupleId = $event->coupleId;

        try {
            CheckMilestonesAction::run($coupleId, CountPlayedCardsForCoupleQuery::run($coupleId));
        } catch (Throwable $exception) {
            Log::warning('Milestone check failed for played cards', [
                'couple_id' => $coupleId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
