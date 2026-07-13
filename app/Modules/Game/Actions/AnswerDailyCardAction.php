<?php

namespace App\Modules\Game\Actions;

use App\Modules\Catalog\Queries\GetDailyQuestionPoolQuery;
use App\Modules\Catalog\Queries\GetQuestionIdByUlidQuery;
use App\Modules\Game\Exceptions\DailyCardAlreadyAnsweredException;
use App\Modules\Game\Exceptions\DailyCardMismatchException;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Support\DailyCard;
use App\Modules\Game\Support\DailyStreakState;
use App\Modules\Memories\Actions\SaveMemoryAction;
use App\Modules\Memories\Data\SaveMemoryInput;
use App\Modules\Memories\Models\Memory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;

/**
 * The full guarded save of a daily-card answer, owned by Game (streak lives on
 * couples). Validates today's card and the not-yet-answered guard, persists the
 * answer through Memories (origin='daily'), and advances the streak — memory and
 * streak in one transaction.
 *
 * Guard failures throw exceptions with render() (409). The question is resolved
 * via Catalog Queries (GetQuestionIdByUlidQuery), NOT by loading Question with
 * Eloquent — the R1 debt in SaveMemoryFromAnswerAction is not copied here.
 *
 * @throws DailyCardMismatchException|DailyCardAlreadyAnsweredException
 */
final class AnswerDailyCardAction
{
    use AsAction;

    /**
     * @return array{0: Memory, 1: Couple}
     */
    public function handle(
        User $user,
        CarbonImmutable $localDate,
        string $questionUlid,
        string $answerA,
        ?string $answerB,
    ): array {
        $couple = Couple::findOrFail($user->active_couple_id);

        $card = new DailyCard($couple->ulid, $localDate);
        $todaysCardId = $card->pick(GetDailyQuestionPoolQuery::run());

        // Guard 1 (race): the submitted card must be today's card for this couple.
        if (GetQuestionIdByUlidQuery::run($questionUlid) !== $todaysCardId) {
            throw new DailyCardMismatchException;
        }

        $state = $card->streakState($couple->last_daily_answered_on);

        // Guard 2: already answered today.
        if ($state === DailyStreakState::AnsweredToday) {
            throw new DailyCardAlreadyAnsweredException;
        }

        return DB::transaction(function () use ($user, $couple, $questionUlid, $answerA, $answerB, $localDate, $state): array {
            // answered_at is the real UTC instant of the answer (like the session
            // flow); last_daily_answered_on is the couple's LOCAL calendar date —
            // two different things, deliberately not interchangeable.
            $memory = SaveMemoryAction::run(new SaveMemoryInput(
                coupleId: $couple->id,
                questionUlid: $questionUlid,
                userId: $user->id,
                playerAName: $user->nickname,
                playerBName: $couple->partner_name_local,
                answerA: $answerA,
                answerB: $answerB,
                gameSessionId: null,
                origin: 'daily',
                answeredAt: now(),
            ));

            // Streak: compute and assign the new current value FIRST, then raise
            // the historical record. AnsweredToday is guarded out above, so the
            // state here is only AliveNotAnswered (continue) or Broken (restart).
            $couple->streak_current = match ($state) {
                DailyStreakState::AliveNotAnswered => $couple->streak_current + 1,
                DailyStreakState::Broken => 1,
            };
            // Store the couple's LOCAL calendar date (date column expects a
            // mutable Carbon).
            $couple->last_daily_answered_on = $localDate->toMutable();

            if ($couple->streak_current > $couple->streak_longest) {
                $couple->streak_longest = $couple->streak_current;
            }

            $couple->save();

            return [$memory, $couple];
        });
    }
}
