<?php

namespace App\Modules\Rewards\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown by the orchestrator when a couple cannot afford what it is buying.
 * Raised from inside the unlock transaction, so it is also what rolls the
 * entitlement write back — the couple ends up with neither the card nor a debit.
 *
 * 422 with the standard errors bag, so the mobile parseApiError shows it under
 * the balance like any other validation message.
 */
final class InsufficientCreditsException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Za mało kredytów.',
            'errors' => ['credits' => ['Za mało kredytów, aby odblokować tę kartę.']],
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
