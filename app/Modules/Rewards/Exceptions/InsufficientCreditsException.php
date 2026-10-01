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
            // The couple never sees the word "credit": the unit is the card.
            'message' => 'Macie za mało kart do odblokowania.',
            'errors' => ['credits' => ['Żeby otworzyć tę kartę, zdobądźcie więcej w Nagrodach.']],
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
