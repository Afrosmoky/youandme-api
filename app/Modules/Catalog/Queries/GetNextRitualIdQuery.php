<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Ritual;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The next ritual to assign: the lowest-ordering ritual NOT in the exclude set,
 * or null when everything is excluded (the pool is exhausted). Catalog does not
 * know about couples — it receives ids to exclude, not a coupleId (same contract
 * as GetSessionQuestionPoolQuery(seenQuestionIds)). The cycle decision (what to do
 * when this returns null) belongs to Game.
 */
final class GetNextRitualIdQuery
{
    use AsAction;

    /**
     * @param  list<int>  $excludeIds
     */
    public function handle(array $excludeIds): ?int
    {
        $query = Ritual::query()
            ->where('locale', 'pl')
            ->orderBy('ordering')
            ->orderBy('id');

        if ($excludeIds !== []) {
            $query->whereNotIn('id', $excludeIds);
        }

        $id = $query->value('id');

        return $id !== null ? (int) $id : null;
    }
}
