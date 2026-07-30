<?php

namespace App\Modules\Game\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a couple pays for a card it already owns. Raised from INSIDE the
 * unlock transaction (the insert reported "nothing new"), so throwing is also
 * what guarantees no credit is charged — the rollback is the guard.
 *
 * 409, not 422: the request is well-formed, the state is simply already there.
 */
final class QuestionAlreadyUnlockedException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Ta karta jest już odblokowana.',
        ], JsonResponse::HTTP_CONFLICT);
    }
}
