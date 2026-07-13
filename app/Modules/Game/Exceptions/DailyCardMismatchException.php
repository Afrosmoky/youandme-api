<?php

namespace App\Modules\Game\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Race guard for the daily card: the submitted question_ulid is not today's card
 * for this couple (the client submitted yesterday's card after the day rolled
 * over in the background). The daily-card twin of StaleCardException. render()
 * returns 409.
 */
final class DailyCardMismatchException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Pytanie nie pasuje do dzisiejszej karty. Pobierz ponownie /daily-card.',
        ], JsonResponse::HTTP_CONFLICT);
    }
}
