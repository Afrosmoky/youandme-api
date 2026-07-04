<?php

namespace App\Modules\Game\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when saving past the end of the session pool. render() reproduces the
 * exact P3 422 body.
 */
final class SessionExhaustedException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Sesja wyczerpana, zakończ ją zanim zapiszesz wspomnienie.',
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
