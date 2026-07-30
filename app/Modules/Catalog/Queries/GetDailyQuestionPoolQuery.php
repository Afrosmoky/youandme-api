<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Question;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The daily-card pool: internal ids of every daily question (type='daily',
 * locale='pl'), ordered by id — a STABLE, deterministic order. Game computes a
 * hash index over this list, so the ordering must be reproducible; a random order
 * would break the per-couple-per-day determinism.
 *
 * Module boundary (doc §5): Catalog returns ids, Game picks and resolves. Same
 * precedent as GetSessionQuestionPoolQuery.
 *
 * The 60/40 closed-deck split (P7) applies to the SESSION deck; daily questions
 * are a separate pool and stay free. The is_locked filter here is purely
 * defensive — it costs nothing and keeps a mis-flipped seed row out of the free
 * daily card (canon §1, note).
 */
final class GetDailyQuestionPoolQuery
{
    use AsAction;

    /**
     * @return list<int>
     */
    public function handle(): array
    {
        /** @var list<int> $ids */
        $ids = Question::query()
            ->where('type', 'daily')
            ->where('locale', 'pl')
            ->where('is_locked', false)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        return $ids;
    }
}
