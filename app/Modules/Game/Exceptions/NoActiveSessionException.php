<?php

namespace App\Modules\Game\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when saving an answer without an active session. render() reproduces the
 * exact P3 422 body (message + errors.session), so moving the guard into the
 * action keeps the error byte-1:1.
 */
final class NoActiveSessionException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Najpierw rozpocznij sesję.',
            'errors' => ['session' => ['Brak aktywnej sesji.']],
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
