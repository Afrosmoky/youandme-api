<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Question;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Resolve a batch of question ulids to the internal ids of the ones that may
 * actually be played: session cards (type='session', locale='pl') that are either
 * free or in the given unlocked list.
 *
 * Batch rather than a loop over GetQuestionIdByUlidQuery: the local-game report
 * arrives as a whole session's worth of cards at once, and one statement instead
 * of N is the entire reason this Query exists next to the single-ulid one.
 *
 * The eligibility filter is the same one GetSessionQuestionPoolQuery applies when
 * building a pool, and it is here for the same reason: this is where the server
 * says WHAT MAY BE PLAYED. The local game sequences client-side, so its report is
 * a client assertion — re-checking it is what keeps a card from the closed deck
 * out of the played set without ever having been unlocked (canon §5).
 *
 * Module boundary (doc §2.2): unlocked ids arrive as a list of values, never a
 * couple — Catalog does not know couple_unlocked_questions exists. Game reads its
 * own table and passes the result in, exactly as with the session pool.
 *
 * Ulids that do not resolve, are not session cards, or are locked-and-not-unlocked
 * are simply absent from the result. The caller decides what that means.
 */
final class GetQuestionIdsByUlidsQuery
{
    use AsAction;

    /**
     * @param  list<string>  $ulids
     * @param  list<int>  $unlockedQuestionIds
     * @return list<int>
     */
    public function handle(array $ulids, array $unlockedQuestionIds = []): array
    {
        if ($ulids === []) {
            return [];
        }

        $query = Question::query()
            ->whereIn('ulid', $ulids)
            ->where('type', 'session')
            ->where('locale', 'pl');

        $query->where(function ($q) use ($unlockedQuestionIds): void {
            $q->where('is_locked', false);

            if ($unlockedQuestionIds !== []) {
                $q->orWhereIn('id', $unlockedQuestionIds);
            }
        });

        /** @var list<int> $ids */
        $ids = $query->pluck('id')->all();

        return $ids;
    }
}
