<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Support\SessionQuestionPool;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds a randomized session pool: eligible session questions (type=session,
 * locale=pl) not in the given seen set, optionally filtered by category, capped
 * at limit. Returns internal ids (Game stores them in session state.remaining_ids).
 *
 * Module boundary (doc §2.2): input is a list of already-seen ids and a list of
 * unlocked ids, NOT a couple — Catalog never touches couple_question_seen or
 * couple_unlocked_questions. Game resolves both from its own tables and passes
 * them in.
 *
 * Closed deck (P7): a locked question is eligible only if this couple unlocked
 * it. The filter lives HERE, at pool-build time, because the pool is frozen into
 * state.remaining_ids by POST /sessions/start (P3 pkt 16) — /questions/next is a
 * pure read and filtering there would either be dead code or yank a card out of a
 * running session. Accepted consequence: a card unlocked mid-session joins the
 * NEXT session (canon §1 row 4).
 *
 * The eligibility filter itself moved to SessionQuestionPool in S4a — unchanged,
 * but now shared with the exhaustion counts, which have to agree with it.
 */
final class GetSessionQuestionPoolQuery
{
    use AsAction;

    /**
     * @param  list<int>  $seenQuestionIds
     * @param  list<int>  $unlockedQuestionIds
     * @return list<int>
     */
    public function handle(
        array $seenQuestionIds,
        array $unlockedQuestionIds = [],
        ?string $categorySlug = null,
        ?int $limit = null,
    ): array {
        $query = SessionQuestionPool::playable($seenQuestionIds, $unlockedQuestionIds);

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
