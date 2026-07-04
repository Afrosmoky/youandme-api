<?php

namespace App\Modules\Game\Actions;

use App\Modules\Catalog\Queries\GetCategoryIdBySlugQuery;
use App\Modules\Catalog\Queries\GetSessionQuestionPoolQuery;
use App\Modules\Game\Events\SessionStarted;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\GameSession;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Creates a fresh session for a couple: resolves the couple's already-seen
 * question ids (Game's own couple_question_seen) and asks Catalog for a random
 * pool of unseen session questions. Returns null when the pool is empty (caller
 * turns that into a 422). The caller guards against an already-active session.
 */
final class StartGameSessionAction
{
    use AsAction;

    /**
     * Number of questions drawn into a fresh session (hardcoded for P3, see
     * docs/third-slice.md section 11.2).
     */
    private const POOL_SIZE = 20;

    public function handle(Couple $couple, ?string $categorySlug): ?GameSession
    {
        /** @var list<int> $seenIds */
        $seenIds = $couple->seenQuestions()->pluck('questions.id')->all();

        $poolIds = GetSessionQuestionPoolQuery::run($seenIds, $categorySlug, self::POOL_SIZE);

        if ($poolIds === []) {
            return null;
        }

        $categoryId = $categorySlug !== null
            ? GetCategoryIdBySlugQuery::run($categorySlug)
            : null;

        $session = GameSession::create([
            'couple_id' => $couple->id,
            'category_id' => $categoryId,
            'mode' => 'local',
            'state' => [
                'remaining_ids' => $poolIds,
                'current_index' => 0,
                'draft_answer' => '',
            ],
            'started_at' => now(),
        ]);

        SessionStarted::dispatch($couple->ulid, $session->ulid);

        return $session;
    }
}
