<?php

namespace App\Modules\Game\Queries;

use App\Modules\Catalog\Queries\GetDailyQuestionPoolQuery;
use App\Modules\Catalog\Queries\GetQuestionByIdQuery;
use App\Modules\Game\Data\DailyCardData;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Support\DailyCard;
use App\Modules\Game\Support\DailyStreakState;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;

/**
 * Assemble the daily card for a couple on their local date: pick today's card
 * (deterministic), resolve its content via Catalog, and lay the streak rule over
 * the raw column. Pure read — "answered today" comes from couples.last_daily_answered_on,
 * so this never has to ask Memories.
 *
 * The local date is a parameter (computed in the controller from users.timezone),
 * keeping this couple-agnostic of Auth internals.
 */
final class GetDailyCardForCoupleQuery
{
    use AsAction;

    public function handle(User $user, CarbonImmutable $localDate): DailyCardData
    {
        $couple = Couple::findOrFail($user->active_couple_id);

        $card = new DailyCard($couple->ulid, $localDate);
        $questionId = $card->pick(GetDailyQuestionPoolQuery::run());

        $question = GetQuestionByIdQuery::run($questionId);
        if ($question === null) {
            // A pool id that no longer resolves — questions are not deleted, so
            // this should never happen. Surface it instead of guessing.
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Pytanie dnia z puli nie istnieje, zgłoś bug.');
        }

        $state = $card->streakState($couple->last_daily_answered_on);

        return new DailyCardData(
            question: $question,
            answeredToday: $state === DailyStreakState::AnsweredToday,
            streakCurrent: $card->effectiveStreak($couple->streak_current, $state),
            streakLongest: $couple->streak_longest,
            dailyPushHour: $couple->daily_push_hour,
        );
    }
}
