<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Game\Actions\UnlockAllLockedQuestionsForCoupleAction;
use App\Modules\Game\Http\Resources\DeckResource;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Queries\GetDeckStateForCoupleQuery;
use App\Modules\Game\Support\UnlockSource;
use App\Modules\Premium\Actions\RedeemPromoCodeAction;
use App\Modules\Premium\Http\Requests\RedeemPromoCodeRequest;
use App\Modules\Premium\Support\PromoCodeKind;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /redeem — turn a promo code into the whole closed deck.
 *
 * The second grantor of the same entitlement (credits were the first), and the
 * second reason the app layer has to hold the orchestration: Premium owns the
 * code, Game owns what a couple may play, and neither calls the other. Both stay
 * leaves; the transaction that makes "code marked used" and "deck opened"
 * inseparable is opened here (canon §5).
 *
 * The couple comes from the token, never from the input (IDOR — canon §5).
 */
final class RedeemController
{
    public function store(RedeemPromoCodeRequest $request): JsonResponse
    {
        $coupleId = $request->user()->active_couple_id;

        if ($coupleId === null) {
            throw new NotFoundHttpException;
        }

        $couple = Couple::findOrFail($coupleId);

        /** @var string $code */
        $code = $request->validated('code');

        $unlocked = DB::transaction(function () use ($coupleId, $code): int {
            // Actions resolve through the laravel-actions static proxy, which is
            // untyped — annotate rather than lose the exhaustive match below.
            /** @var PromoCodeKind $kind */
            $kind = RedeemPromoCodeAction::run($coupleId, $code);

            // One kind today; themed packs arrive with their content and will
            // branch here rather than inside either module.
            return match ($kind) {
                PromoCodeKind::FullDeck => UnlockAllLockedQuestionsForCoupleAction::run(
                    $coupleId,
                    UnlockSource::Premium,
                ),
            };
        });

        return response()->json([
            // How many cards this redemption actually added — zero is a perfectly
            // good answer for a couple that had already bought them all.
            'unlocked' => $unlocked,
            'deck' => new DeckResource(GetDeckStateForCoupleQuery::run($couple)),
        ]);
    }
}
