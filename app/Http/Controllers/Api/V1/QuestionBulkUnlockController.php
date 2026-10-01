<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NotEnoughCardsToUnlockException;
use App\Http\Requests\UnlockQuestionsRequest;
use App\Modules\Catalog\Queries\GetSessionQuestionLocksByUlidsQuery;
use App\Modules\Catalog\Support\SessionQuestionLock;
use App\Modules\Game\Actions\UnlockQuestionsForCoupleAction;
use App\Modules\Game\Http\Resources\DeckResource;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Queries\GetDeckStateForCoupleQuery;
use App\Modules\Game\Support\UnlockSource;
use App\Modules\Rewards\Actions\SpendCreditsAction;
use App\Modules\Rewards\Queries\GetCreditBalanceQuery;
use App\Support\CardUnlockPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /questions/unlock — buy several cards of the closed deck in one request,
 * for a couple that would otherwise tap through cards it cannot read one by one.
 * The single-card POST /questions/{ulid}/unlock stays as it was for released
 * builds.
 *
 * App composition root for the same reason as the single unlock: Rewards owns the
 * balance, Game the entitlement, and only this layer may span both in one DB
 * transaction.
 *
 * The contract, and why:
 * - A list of ulids, not a count. Retrying the same list after a lost response is
 *   free (owned cards are skipped), where "unlock 5" retried would charge twice.
 * - All or nothing. If the balance does not cover every NEW card, nothing is
 *   unlocked and nothing charged (422); the client knows the balance and trims.
 * - Already owned or no longer locked: skipped, never charged, reported back.
 * - Charge = price × rows this transaction actually inserted (RETURNING), never
 *   the length of the list.
 *
 * Concurrency follows the single unlock: WRITE FIRST, CHARGE SECOND, and the
 * balance row is always the last lock taken. Entitlement rows go in ascending id
 * order, so two bulk requests over overlapping cards queue instead of
 * deadlocking; the conditional decrement plus CHECK (credits >= 0) keep the
 * balance from going below zero. No up-front SELECT ... FOR UPDATE on the
 * balance — taking it first here while the single unlock takes it last would
 * be exactly the lock-order inversion that deadlocks.
 *
 * The couple always comes from the token, never from the input (IDOR — canon §5).
 */
final class QuestionBulkUnlockController
{
    public function store(UnlockQuestionsRequest $request): JsonResponse
    {
        $coupleId = $request->user()->active_couple_id;

        if ($coupleId === null) {
            throw new NotFoundHttpException;
        }

        $couple = Couple::findOrFail($coupleId);

        $ulids = $request->questionUlids();
        $locks = GetSessionQuestionLocksByUlidsQuery::run($ulids);

        // A ulid that is not a session card is a client bug (the deck only ever
        // lists real cards) — refuse the whole request before anything is written.
        $unknown = array_values(array_diff($ulids, array_keys($locks)));

        if ($unknown !== []) {
            $requested = $request->validated('question_ulids');

            throw ValidationException::withMessages(array_combine(
                array_map(fn (string $ulid): string => 'question_ulids.'.array_search($ulid, $requested, true), $unknown),
                array_fill(0, count($unknown), 'Nie ma takiej karty.'),
            ));
        }

        $forSale = array_filter($locks, fn (SessionQuestionLock $lock): bool => $lock->isLocked);

        $unlockedIds = DB::transaction(function () use ($coupleId, $forSale): array {
            $inserted = UnlockQuestionsForCoupleAction::run(
                $coupleId,
                array_values(array_map(fn (SessionQuestionLock $lock): int => $lock->id, $forSale)),
                UnlockSource::Credits,
            );

            $charge = count($inserted) * CardUnlockPrice::CREDITS;

            // Nothing new → nothing to charge, and no debit call at all: for a
            // couple with no reward account yet the conditional decrement matches
            // no row and would read as "not enough credits" for a free operation.
            if ($charge > 0 && ! SpendCreditsAction::run($coupleId, $charge)) {
                throw new NotEnoughCardsToUnlockException(
                    required: $charge,
                    credits: GetCreditBalanceQuery::run($coupleId),
                );
            }

            return $inserted;
        });

        $unlocked = array_flip($unlockedIds);
        $unlockedUlids = [];
        $skipped = [];

        // Report in request order, so the client can map the answer onto its own
        // selection without sorting.
        foreach ($ulids as $ulid) {
            $lock = $locks[$ulid];

            if (! $lock->isLocked) {
                $skipped[] = ['ulid' => $ulid, 'reason' => 'not_locked'];
            } elseif (isset($unlocked[$lock->id])) {
                $unlockedUlids[] = $ulid;
            } else {
                $skipped[] = ['ulid' => $ulid, 'reason' => 'already_unlocked'];
            }
        }

        $deck = GetDeckStateForCoupleQuery::run($couple);

        return response()->json([
            'charged' => count($unlockedIds) * CardUnlockPrice::CREDITS,
            'credits' => GetCreditBalanceQuery::run($coupleId),
            'unlocked' => $unlockedUlids,
            'skipped' => $skipped,
            'locked_remaining' => $deck->lockedRemaining(),
            'deck' => new DeckResource($deck),
        ]);
    }
}
