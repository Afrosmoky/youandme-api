<?php

namespace App\Modules\Game\Http\Controllers;

use App\Modules\Catalog\Data\QuestionData;
use App\Modules\Catalog\Queries\ListQuestionsByIdsQuery;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Queries\ListLikedQuestionPageForCoupleQuery;
use App\Modules\Game\Support\QuestionCardPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /questions/liked — the cards this couple has hearted.
 *
 * The half of the like feature that was missing since P5: the heart wrote a row
 * and had nowhere to surface, so from the couple's side clicking it did nothing
 * (beta feedback B1 — a tester clicked hers several times, reasonably). The
 * client gets a tab for it in build 3; the endpoint ships ahead of the app.
 *
 * A Game aggregate (which cards did this couple heart) with the content pulled
 * downstream from Catalog — not peer-combining two modules' resources — so it
 * stays in Game, like /questions/deck and /weekly-ritual.
 *
 * The couple comes from the token, never from input. The list is filtered to
 * cards the couple may actually open, which is enforced in the Query.
 *
 * Deliberately NOT the same list as GET /memories?favorites=1: a hearted
 * QUESTION and a favourite MEMORY are two different things under one icon, and
 * the client shows them as two tabs.
 */
final class LikedQuestionController
{
    public function index(Request $request): JsonResponse
    {
        $couple = Couple::findOrFail($request->user()->active_couple_id);

        // Clamped, not rejected — the treatment ?per_page gets on the memories
        // list, whose pagination contract this endpoint reuses wholesale.
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = max(1, min($perPage, 50));

        $page = ListLikedQuestionPageForCoupleQuery::run(
            $couple,
            $perPage,
            $request->string('cursor')->toString() ?: null,
        );

        // Resolved in one batch, in the order the likes came back — the same
        // resolver the deck uses, so a page of any size costs one lookup plus
        // its categories, not one per card.
        $questions = ListQuestionsByIdsQuery::run($page->questionIds);

        return response()->json([
            'data' => array_map(
                // liked is true for every card here by construction. It is still
                // written out, because the client renders this list with the same
                // card component it uses for the deck, and that card reads the key.
                fn (QuestionData $question): array => QuestionCardPayload::build($question, true),
                $questions,
            ),
            'meta' => [
                'next_cursor' => $page->nextCursor,
                'prev_cursor' => $page->prevCursor,
                'per_page' => $page->perPage,
            ],
        ]);
    }
}
