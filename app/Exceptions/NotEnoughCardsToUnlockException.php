<?php

namespace App\Exceptions;

use App\Support\CardCount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown by the bulk unlock when the balance does not cover every new card in the
 * request. Raised from inside the transaction, so it is also what rolls the
 * entitlement rows back — all or nothing: no card unlocked, nothing charged.
 *
 * Same 422 and the same errors.credits field as InsufficientCreditsException
 * (the single unlock), so the client shows it in the same place. The body also
 * carries both numbers, because the client's balance may be stale and it needs
 * them to trim the selection.
 *
 * The field sentence holds two numbers, so each is declined on its own
 * (CardCount) — "kosztuje 5 kart, a macie 2 karty".
 */
final class NotEnoughCardsToUnlockException extends RuntimeException
{
    public function __construct(
        public readonly int $required,
        public readonly int $credits,
    ) {
        parent::__construct('Not enough credits to unlock the requested cards.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Macie za mało kart do odblokowania.',
            'errors' => ['credits' => [sprintf(
                'Odblokowanie wybranych kart kosztuje %s, a macie %s.',
                CardCount::accusative($this->required),
                CardCount::accusative($this->credits),
            )]],
            'credits' => $this->credits,
            'required' => $this->required,
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
