<?php

namespace App\Modules\Game\Data;

use App\Modules\Catalog\Data\QuestionData;
use Spatie\LaravelData\Data;

/**
 * Read contract for GET /daily-card: today's question (pulled downstream from
 * Catalog as QuestionData) plus the couple's daily state. streakCurrent is the
 * EFFECTIVE streak (0 when broken), not the raw column. liked is the couple's
 * like state for today's card — a Game concern carried on this Game DTO, not on
 * Catalog's QuestionData.
 */
final class DailyCardData extends Data
{
    public function __construct(
        public readonly QuestionData $question,
        public readonly bool $liked,
        public readonly bool $answeredToday,
        public readonly int $streakCurrent,
        public readonly int $streakLongest,
        public readonly int $dailyPushHour,
    ) {}
}
