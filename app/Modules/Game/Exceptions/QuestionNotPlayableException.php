<?php

namespace App\Modules\Game\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a card cannot be played by this couple — it is not a session card,
 * or it belongs to the closed deck and they have not unlocked it.
 *
 * 422 rather than 403: nothing was forbidden to the requester as such, the card
 * simply is not part of the deck they were dealt. A well-behaved client cannot
 * reach this, because its deck came from the server already filtered.
 *
 * render() keeps the shape every other Game guard uses, so the app controller
 * stays thin.
 */
final class QuestionNotPlayableException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Tej karty nie ma w waszej talii.',
            'errors' => ['question_ulid' => ['Karta jest niedostępna dla tej pary.']],
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
