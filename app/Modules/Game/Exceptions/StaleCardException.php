<?php

namespace App\Modules\Game\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown by the race guard when the submitted question_ulid does not match the
 * session's current card (a stale card after a refresh). render() reproduces the
 * exact P3 409 body.
 */
final class StaleCardException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Pytanie nie pasuje do aktualnej karty sesji. Pobierz ponownie /questions/next.',
        ], JsonResponse::HTTP_CONFLICT);
    }
}
