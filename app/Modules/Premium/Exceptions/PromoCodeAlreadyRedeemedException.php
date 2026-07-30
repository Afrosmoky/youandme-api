<?php

namespace App\Modules\Premium\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * This couple has already redeemed this code. Thrown from inside the redeem
 * transaction (the register insert reported "nothing new"), so throwing is also
 * what stops a second use being counted against a limited code.
 *
 * 409, not 422: the code is fine, the state is simply already there — the same
 * distinction as unlocking a card the couple owns.
 */
final class PromoCodeAlreadyRedeemedException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Ten kod jest już zrealizowany na tym koncie.',
        ], JsonResponse::HTTP_CONFLICT);
    }
}
