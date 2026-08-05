<?php

namespace App\Modules\Game\Http\Controllers;

use App\Modules\Catalog\Queries\GetQuestionIdsByUlidsQuery;
use App\Modules\Game\Actions\MarkQuestionsPlayedAction;
use App\Modules\Game\Http\Requests\ReportPlayedCardsRequest;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Queries\CountPlayedCardsForCoupleQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * POST /game/local/report — the local game's one call home (P10).
 *
 * The game itself runs on the phone: the client holds the state in MMKV,
 * sequences the deck and interleaves the challenge cards, so the server learns
 * what was played only afterwards, in one batch. There is no server-side local
 * session to report INTO — that is the whole point of the client-authoritative
 * decision (canon ◆A), and it is why this is a batch command rather than N events.
 *
 * The couple comes from the token, never from input (IDOR — canon §5): a report
 * says "we played these", it can never say "that couple played these".
 *
 * Returns Game data only ({played_total, newly_played}), nothing peer-combined,
 * so the endpoint belongs in the module rather than the app layer.
 *
 * Cards that fail the eligibility check are dropped instead of failing the batch:
 * the couple really did play their session, and one unusable ulid is not a reason
 * to lose the rest. It is logged, because a well-behaved client cannot produce
 * one — its deck came from the server already filtered.
 */
final class LocalGameController
{
    public function report(ReportPlayedCardsRequest $request): JsonResponse
    {
        $couple = Couple::findOrFail($request->user()->active_couple_id);

        /** @var list<string> $ulids */
        $ulids = $request->validated('question_ulids');

        // The couple's entitlement, read here and passed as values — Catalog
        // re-applies the pool filter but must not learn what a couple is.
        /** @var list<int> $unlockedIds */
        $unlockedIds = $couple->unlockedQuestions()->pluck('questions.id')->all();

        $playableIds = GetQuestionIdsByUlidsQuery::run($ulids, $unlockedIds);

        if (count($playableIds) !== count($ulids)) {
            Log::warning('Local game report contained cards this couple may not play', [
                'couple_id' => $couple->id,
                'reported' => count($ulids),
                'accepted' => count($playableIds),
            ]);
        }

        $newlyPlayed = MarkQuestionsPlayedAction::run($couple, $playableIds);

        return response()->json([
            'played_total' => CountPlayedCardsForCoupleQuery::run($couple->id),
            'newly_played' => $newlyPlayed,
        ]);
    }
}
