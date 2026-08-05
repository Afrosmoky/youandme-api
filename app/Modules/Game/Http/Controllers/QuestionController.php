<?php

namespace App\Modules\Game\Http\Controllers;

use App\Modules\Catalog\Data\QuestionData;
use App\Modules\Catalog\Queries\GetSessionQuestionPoolQuery;
use App\Modules\Catalog\Queries\ListQuestionsByIdsQuery;
use App\Modules\Game\Http\Requests\DeckQuestionsRequest;
use App\Modules\Game\Http\Resources\SessionResource;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Queries\GetNextQuestionInSessionQuery;
use App\Modules\Game\Queries\IsQuestionLikedByCoupleQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class QuestionController
{
    /** Cards handed out when the client does not ask for a number. */
    private const DEFAULT_DECK_SIZE = 40;

    /**
     * Hard ceiling on one deck. Matched to the report cap: a client must never be
     * dealt more cards than it can report back in a single batch, or a long local
     * session would silently split its progress across two calls.
     */
    private const MAX_DECK_SIZE = 100;

    public function next(Request $request): JsonResponse
    {
        $couple = Couple::findOrFail($request->user()->active_couple_id);
        $session = $couple->gameSessions()->active()->first();

        if ($session === null) {
            return response()->json([
                'message' => 'Najpierw rozpocznij sesję.',
                'errors' => ['session' => ['Brak aktywnej sesji.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $state = $session->state;
        /** @var list<int> $remainingIds */
        $remainingIds = is_array($state['remaining_ids'] ?? null) ? $state['remaining_ids'] : [];
        $currentIndex = isset($state['current_index']) ? (int) $state['current_index'] : 0;

        // Pure read: /next never advances current_index — only save or skip does.
        if ($currentIndex >= count($remainingIds)) {
            return response()->json([
                'question' => null,
                'session_complete' => true,
                'session' => new SessionResource($session),
            ]);
        }

        $question = GetNextQuestionInSessionQuery::run($session);

        if ($question === null) {
            // The current id no longer resolves — should never happen (questions
            // are not deleted). Surface it instead of guessing.
            Log::error('Question from session pool not found', [
                'session_id' => $session->id,
                'question_id' => $remainingIds[$currentIndex],
            ]);

            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Pytanie z puli nie istnieje, zgłoś bug.');
        }

        // The served card's internal id is already in the pool — no ulid
        // resolution needed to check the like state (single PK lookup, no N+1).
        $liked = IsQuestionLikedByCoupleQuery::run($couple, (int) $remainingIds[$currentIndex]);

        return response()->json([
            'question' => $this->questionPayload($question, $liked),
            'session' => new SessionResource($session),
        ]);
    }

    /**
     * GET /questions/deck — a whole playable deck in one call (P10).
     *
     * The local game runs on the phone: it sequences the cards, interleaves the
     * challenges and keeps its place in MMKV, so it needs the cards up front
     * rather than one at a time. /questions/next cannot serve that — it reads from
     * an active server session, and creating one would put back exactly the
     * server-side state the client-authoritative decision removed (canon ◆A).
     *
     * What it is NOT is a second pool rule. It calls the same
     * GetSessionQuestionPoolQuery that POST /sessions/start freezes into a session,
     * with the same two lists read from Game's own tables — so the deck a phone
     * gets is the deck the server would have dealt. That is the security line
     * (canon §5): the server says what may be played, the client only in which
     * order. A card the couple has not unlocked never leaves this endpoint, so no
     * amount of client-side sequencing can reach it.
     *
     * The deck is NOT recorded as played here. Reading is not playing — the couple
     * may put the phone down after two cards — and the report is what says
     * otherwise (CQS: this endpoint writes nothing).
     *
     * Unrelated to GET /deck despite the name: that one is the closed-deck
     * entitlement ("which locked cards do we own"), this one is content to play.
     */
    public function deck(DeckQuestionsRequest $request): JsonResponse
    {
        $couple = Couple::findOrFail($request->user()->active_couple_id);

        /** @var list<int> $seenIds */
        $seenIds = $couple->seenQuestions()->pluck('questions.id')->all();

        /** @var list<int> $unlockedIds */
        $unlockedIds = $couple->unlockedQuestions()->pluck('questions.id')->all();

        // Clamped, not rejected — the same treatment ?per_page gets on the
        // memories list. A client asking for 500 cards gets the ceiling; a client
        // asking for none gets one.
        $limit = (int) $request->integer('limit', self::DEFAULT_DECK_SIZE);
        $limit = max(1, min($limit, self::MAX_DECK_SIZE));

        $poolIds = GetSessionQuestionPoolQuery::run(
            $seenIds,
            $unlockedIds,
            $request->string('category')->toString() ?: null,
            $limit,
        );

        // Resolved in one batch, not card by card: a hundred cards would
        // otherwise be a hundred round trips plus their categories.
        $questions = ListQuestionsByIdsQuery::run($poolIds);

        if (count($questions) !== count($poolIds)) {
            // The pool just returned these ids, so none of them can have
            // vanished. Ship the deck anyway — a couple mid-setup should not be
            // stopped by our bookkeeping — but say so.
            Log::error('Deck pool contained ids that no longer resolve', [
                'requested' => count($poolIds),
                'resolved' => count($questions),
            ]);
        }

        // An exhausted deck is an empty list, not an error: a couple that has
        // played everything in a category has succeeded at the game, and the
        // client shows them that rather than a failure.
        return response()->json([
            'questions' => array_map(fn (QuestionData $question): array => $this->questionPayload($question), $questions),
        ]);
    }

    /**
     * Reproduce the Catalog QuestionResource shape from QuestionData (byte-1:1
     * with P3) — category trimmed to slug + name — plus, inside a session, the
     * couple's like state (added additively; liked is a Game concern, not part of
     * QuestionData).
     *
     * One builder for both endpoints so the card cannot drift into two shapes.
     * The deck omits `liked` and nothing else: hearts are per couple and the
     * client fetches them separately, while the deck is a content listing.
     *
     * is_locked rides along for the client's "unlocked" badge. In both places it
     * reads as "this couple paid for this card": the pool only ever contains free
     * cards plus the ones they unlocked, so a locked card being served IS an
     * unlocked one (GetSessionQuestionPoolQuery).
     *
     * @return array<string, mixed>
     */
    private function questionPayload(QuestionData $question, ?bool $liked = null): array
    {
        $payload = [
            'ulid' => $question->ulid,
            'body' => $question->body,
            'type' => $question->type,
            'category' => $question->category ? [
                'slug' => $question->category->slug,
                'name' => $question->category->name,
            ] : null,
            'tags' => $question->tags,
        ];

        if ($liked !== null) {
            $payload['liked'] = $liked;
        }

        // Last, so /questions/next keeps the exact key order it has had since P3.
        $payload['is_locked'] = $question->isLocked;

        return $payload;
    }
}
