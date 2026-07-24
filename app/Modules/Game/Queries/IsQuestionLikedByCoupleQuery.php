<?php

namespace App\Modules\Game\Queries;

use App\Modules\Game\Models\Couple;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Whether a couple has liked a given question — a single existence check on the
 * couple_question_likes composite PK. Takes the internal question id (callers
 * that serve a question already hold it: remaining_ids[current_index] for a
 * session, the resolved daily-card id), so no ulid resolution and no N+1. Reads
 * the pivot directly (no join to questions) — Game holds question_id via FK.
 */
final class IsQuestionLikedByCoupleQuery
{
    use AsAction;

    public function handle(Couple $couple, int $questionId): bool
    {
        return DB::table('couple_question_likes')
            ->where('couple_id', $couple->id)
            ->where('question_id', $questionId)
            ->exists();
    }
}
