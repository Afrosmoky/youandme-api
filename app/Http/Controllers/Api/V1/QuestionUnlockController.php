<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Catalog\Queries\GetQuestionIdByUlidQuery;
use App\Modules\Catalog\Queries\IsQuestionLockedQuery;
use App\Modules\Game\Actions\UnlockQuestionForCoupleAction;
use App\Modules\Game\Exceptions\QuestionAlreadyUnlockedException;
use App\Modules\Game\Http\Resources\DeckResource;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Queries\GetDeckStateForCoupleQuery;
use App\Modules\Game\Support\UnlockSource;
use App\Modules\Rewards\Actions\SpendCreditsAction;
use App\Modules\Rewards\Exceptions\InsufficientCreditsException;
use App\Modules\Rewards\Queries\GetCreditBalanceQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /questions/{questionUlid}/unlock — buy one card of the closed deck for a
 * credit.
 *
 * App composition root, because the operation spans two modules that must not
 * know each other: Rewards owns the balance, Game owns the entitlement. Neither
 * may call the other (both are leaves / one-way), so the orchestration — and the
 * DB transaction that makes it atomic — lives here (canon §5; guide 4.1).
 *
 * Order inside the transaction is deliberate: WRITE FIRST, CHARGE SECOND. The
 * insert is the concurrency guard (ON CONFLICT DO NOTHING → "was it new?"), so a
 * duplicate unlock is rejected before any credit leaves the balance, and two
 * simultaneous requests for the same card cost one credit, not two. A failed
 * debit rolls the entitlement back — the couple never ends up with a free card
 * or a paid-for nothing.
 *
 * The couple always comes from the token, never from the input (IDOR — canon §5).
 */
final class QuestionUnlockController
{
    /**
     * Wiktoria's economy: 1 credit = 1 locked card. A product number, and the
     * app layer is where the two modules meet — Rewards must not learn the price
     * of a card, Game must not learn that cards cost credits at all.
     */
    private const UNLOCK_COST = 1;

    public function store(Request $request, string $questionUlid): JsonResponse
    {
        $coupleId = $request->user()->active_couple_id;

        if ($coupleId === null) {
            throw new NotFoundHttpException;
        }

        $couple = Couple::findOrFail($coupleId);

        $questionId = GetQuestionIdByUlidQuery::run($questionUlid);

        if ($questionId === null) {
            throw new NotFoundHttpException;
        }

        // A free card is not for sale — refusing protects the couple from paying
        // for something it can already play. Checked before the transaction: it is
        // a stable fact about the content, not a race.
        if (! IsQuestionLockedQuery::run($questionId)) {
            return response()->json([
                'message' => 'Ta karta jest dostępna bez odblokowywania.',
                'errors' => ['question' => ['Ta karta nie jest zamknięta.']],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::transaction(function () use ($coupleId, $questionId): void {
            if (! UnlockQuestionForCoupleAction::run($coupleId, $questionId, UnlockSource::Credits)) {
                throw new QuestionAlreadyUnlockedException;
            }

            if (! SpendCreditsAction::run($coupleId, self::UNLOCK_COST)) {
                throw new InsufficientCreditsException;
            }
        });

        // Answer with the state the client would otherwise refetch twice.
        return response()->json([
            'credits' => GetCreditBalanceQuery::run($coupleId),
            'deck' => new DeckResource(GetDeckStateForCoupleQuery::run($couple)),
        ]);
    }
}
