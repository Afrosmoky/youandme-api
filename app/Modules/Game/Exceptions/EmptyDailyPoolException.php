<?php

namespace App\Modules\Game\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when the daily-card pool is empty (the seed did not run). Guards the
 * modulo in DailyCard::pick() against a division by zero — a silent 500 is a bad
 * way to learn the seed is missing. render() returns 422, mirroring
 * SessionExhaustedException.
 */
final class EmptyDailyPoolException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Brak pytań dnia w bazie. Uruchom seeder pytań dnia.',
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
