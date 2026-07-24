<?php

namespace App\Modules\Game\Http\Controllers;

use App\Modules\Catalog\Data\QuestionData;
use App\Modules\Game\Queries\GetDailyCardForCoupleQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /daily-card — a Game aggregate (the couple's day: card, streak, answered?)
 * with the question pulled downstream from Catalog. Not peer-combining, so it
 * stays in Game — same shape as GET /questions/next. POST /daily-card/answer
 * (which returns {memory, couple}) lives in the app layer.
 *
 * The couple's local date is resolved here from users.timezone and passed to the
 * Query as a plain date, so the Query stays timezone-agnostic.
 */
final class DailyCardController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $localDate = CarbonImmutable::now($user->timezone)->startOfDay();

        $card = GetDailyCardForCoupleQuery::run($user, $localDate);

        return response()->json([
            'question' => $this->questionPayload($card->question),
            'liked' => $card->liked,
            'answered_today' => $card->answeredToday,
            'streak_current' => $card->streakCurrent,
            'streak_longest' => $card->streakLongest,
            'daily_push_hour' => $card->dailyPushHour,
        ]);
    }

    /**
     * Reproduce the Catalog QuestionResource shape from QuestionData — the same
     * payload as GET /questions/next (category trimmed to slug + name; null for
     * daily questions).
     *
     * @return array<string, mixed>
     */
    private function questionPayload(QuestionData $question): array
    {
        return [
            'ulid' => $question->ulid,
            'body' => $question->body,
            'type' => $question->type,
            'category' => $question->category ? [
                'slug' => $question->category->slug,
                'name' => $question->category->name,
            ] : null,
            'tags' => $question->tags,
        ];
    }
}
