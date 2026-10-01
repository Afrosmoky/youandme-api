<?php

namespace App\Exceptions;

use App\Support\CardCount;
use App\Support\CardUnlockPrice;
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
 * The field sentence talks in cards on both sides, never in a price: the balance
 * IS a number of cards in the interface, so "cards cost N cards" would read as
 * nonsense. Only the first number carries a noun (declined by CardCount); the
 * second stands alone — "Wybraliście 5 kart, a możecie odblokować 2." Both are
 * converted from credits by the card price, so the sentence stays true if the
 * price ever stops being 1.
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
                'Wybraliście %s, a możecie odblokować %d.',
                CardCount::accusative(intdiv($this->required, CardUnlockPrice::CREDITS)),
                intdiv($this->credits, CardUnlockPrice::CREDITS),
            )]],
            'credits' => $this->credits,
            'required' => $this->required,
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
