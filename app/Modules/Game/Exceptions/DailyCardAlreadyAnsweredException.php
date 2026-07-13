<?php

namespace App\Modules\Game\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a couple tries to answer today's daily card twice
 * (last_daily_answered_on is already today). render() returns 409.
 */
final class DailyCardAlreadyAnsweredException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Karta dnia jest już odpowiedziana.',
        ], JsonResponse::HTTP_CONFLICT);
    }
}
