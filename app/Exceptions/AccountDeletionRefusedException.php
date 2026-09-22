<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The account belongs to a couple somebody else is in too, so deleting it would
 * mean deciding what happens to half of a shared history. Nothing in the app puts
 * a second person into a couple yet (the remote game is still to come); this is
 * the answer until that decision is made, instead of a foreign-key 500.
 *
 * 409: the request is fine, the account's state is what stands in the way.
 */
final class AccountDeletionRefusedException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Tego konta nie da się jeszcze usunąć samodzielnie — napisz do nas na kontakt@jaity.app.',
        ], JsonResponse::HTTP_CONFLICT);
    }
}
