<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Game\Actions\AnswerDailyCardAction;
use App\Modules\Game\Http\Requests\AnswerDailyCardRequest;
use App\Modules\Game\Http\Resources\CoupleResource;
use App\Modules\Memories\Http\Resources\MemoryResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * App composition root for POST /daily-card/answer — the answer peer-combines a
 * Memories resource ({memory}) and a Game resource ({couple}, carrying the fresh
 * streak), so it lives in the app layer (same rule as POST /memories). Thin: the
 * whole guarded save + streak update is owned by Game\AnswerDailyCardAction.
 *
 * The couple's local date is resolved here from users.timezone and passed to the
 * Action as a plain date, so the Action stays timezone-agnostic.
 */
final class DailyCardController
{
    public function answer(AnswerDailyCardRequest $request): JsonResponse
    {
        $user = $request->user();
        $localDate = CarbonImmutable::now($user->timezone)->startOfDay();
        $answerB = $request->string('answer_b')->toString();

        [$memory, $couple] = AnswerDailyCardAction::run(
            $user,
            $localDate,
            $request->string('question_ulid')->toString(),
            $request->string('answer_a')->toString(),
            $answerB !== '' ? $answerB : null,
        );

        return response()->json([
            'memory' => new MemoryResource($memory),
            'couple' => new CoupleResource($couple),
        ], Response::HTTP_CREATED);
    }
}
