<?php

namespace App\Modules\Premium\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The code cannot be redeemed: it does not exist, it has expired, or its usage
 * budget is spent.
 *
 * One class with three named constructors rather than three classes: the caller
 * treats them identically (422 with the standard errors bag, as canon §6 asks of
 * the mobile parseApiError) and only the message differs. What must NOT be merged
 * in here is "already redeemed by this couple" — that is a 409 about state, not a
 * complaint about the code.
 *
 * The reason is deliberately visible to the user: "this code expired" and "this
 * code does not exist" are different problems for someone retyping from a card,
 * and neither leaks anything a guesser could not learn by trying.
 */
final class InvalidPromoCodeException extends RuntimeException
{
    private function __construct(private readonly string $userMessage)
    {
        parent::__construct($userMessage);
    }

    public static function unknown(): self
    {
        return new self('Nie znamy takiego kodu.');
    }

    public static function expired(): self
    {
        return new self('Ten kod już wygasł.');
    }

    public static function exhausted(): self
    {
        return new self('Ten kod został już wykorzystany.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->userMessage,
            'errors' => ['code' => [$this->userMessage]],
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
