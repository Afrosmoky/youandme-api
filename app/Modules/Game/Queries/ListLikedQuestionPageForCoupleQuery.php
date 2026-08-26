<?php

namespace App\Modules\Game\Queries;

use App\Modules\Catalog\Queries\ListLockedQuestionIdsQuery;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Support\LikedQuestionPage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * One page of the couple's hearted cards, newest heart first.
 *
 * Reads couple_question_likes directly (Game's own table, no Eloquent model —
 * same as IsQuestionLikedByCoupleQuery) and returns internal ids; the content is
 * resolved afterwards through Catalog, so Game never loads Question itself.
 *
 * Entitlement is applied HERE, before pagination, not after: a card filtered out
 * of an already-cut page would leave holes and a short last page. Catalog says
 * which cards are locked, Game says which of those this couple owns, and the
 * difference is what never leaves the endpoint (canon §5 — "is it locked" is a
 * content fact, "did they unlock it" is a Game fact). Cards outside the closed
 * deck — free session cards and every daily card — are untouched by the filter,
 * which is why a hearted daily card still shows up.
 *
 * The cursor pairs liked_at with question_id so hearts sharing a timestamp are
 * not skipped between pages — the same tiebreaker the memories list uses id for.
 */
final class ListLikedQuestionPageForCoupleQuery
{
    use AsAction;

    public function handle(Couple $couple, int $perPage, ?string $cursor): LikedQuestionPage
    {
        /** @var list<int> $unlockedIds */
        $unlockedIds = $couple->unlockedQuestions()->pluck('questions.id')->all();

        $withheldIds = array_values(array_diff(ListLockedQuestionIdsQuery::run(), $unlockedIds));

        $paginator = DB::table('couple_question_likes')
            ->where('couple_id', $couple->id)
            ->when($withheldIds !== [], fn ($query) => $query->whereNotIn('question_id', $withheldIds))
            ->select('question_id', 'liked_at')
            ->orderByDesc('liked_at')
            ->orderByDesc('question_id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor);

        /** @var list<int> $questionIds */
        $questionIds = array_map(
            fn (object $row): int => (int) $row->question_id,
            $paginator->items(),
        );

        return new LikedQuestionPage(
            questionIds: $questionIds,
            nextCursor: $paginator->nextCursor()?->encode(),
            prevCursor: $paginator->previousCursor()?->encode(),
            perPage: $paginator->perPage(),
        );
    }
}
