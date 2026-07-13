<?php

namespace App\Modules\Game\Support;

/**
 * The three states of a couple's daily streak, derived (not stored) from
 * last_daily_answered_on and the couple's local date. Three, not two:
 *
 * - AnsweredToday    — last answer is from today
 * - AliveNotAnswered — last answer is from yesterday; today still open (the
 *                      "answer today" tile that schedules the evening warning)
 * - Broken           — a full day passed with no answer (today - last > 1 day),
 *                      or no answer ever
 */
enum DailyStreakState
{
    case AnsweredToday;
    case AliveNotAnswered;
    case Broken;
}
