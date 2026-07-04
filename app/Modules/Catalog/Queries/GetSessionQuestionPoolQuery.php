<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Question;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds a randomized session pool: eligible session questions (type=session,
 * locale=pl) not in the given seen set, optionally filtered by category, capped
 * at limit. Returns internal ids (Game stores them in session state.remaining_ids).
 *
 * Module boundary (doc §2.2): input is a list of already-seen ids, NOT a couple —
 * Catalog never touches couple_question_seen. Game resolves the seen ids from its
 * own pivot and passes them in.
 */
final class GetSessionQuestionPoolQuery
{
    use AsAction;

    /**
     * @param  list<int>  $seenQuestionIds
     * @return list<int>
     */
    public function handle(array $seenQuestionIds, ?string $categorySlug = null, ?int $limit = null): array
    {
        $query = Question::query()
            ->where('type', 'session')
            ->where('locale', 'pl');

        if ($seenQuestionIds !== []) {
            $query->whereNotIn('id', $seenQuestionIds);
        }

        if ($categorySlug !== null) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        $query->inRandomOrder();

        if ($limit !== null) {
            $query->limit($limit);
        }

        /** @var list<int> $ids */
        $ids = $query->pluck('id')->all();

        return $ids;
    }
}
