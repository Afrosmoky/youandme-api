<?php

namespace App\Modules\Memories\Actions;

use App\Modules\Catalog\Queries\GetQuestionIdByUlidQuery;
use App\Modules\Memories\Data\MemoryCreatedData;
use App\Modules\Memories\Data\SaveMemoryInput;
use App\Modules\Memories\Events\MemoryCreated;
use App\Modules\Memories\Models\Memory;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Persist a memory (the write that Game::SaveMemoryFromAnswerAction delegates
 * here). Resolves the question id from its ulid via Catalog, creates the record,
 * emits MemoryCreated.
 *
 * Returns the Eloquent model (not MemoryData) so the caller can serialize the
 * byte-1:1 HTTP response via MemoryResource — the same model-return pattern as
 * Game\CreateCoupleForUserAction (R1 Etap 4). MemoryData is the read Public API
 * (GetMemoryByUlidQuery / ListMemoriesForCoupleQuery).
 */
final class SaveMemoryAction
{
    use AsAction;

    public function handle(SaveMemoryInput $input): Memory
    {
        $memory = Memory::create([
            'couple_id' => $input->coupleId,
            'user_id' => $input->userId,
            'question_id' => GetQuestionIdByUlidQuery::run($input->questionUlid),
            'game_session_id' => $input->gameSessionId,
            'origin' => $input->origin,
            'answer_a' => $input->answerA,
            'answer_b' => $input->answerB,
            'player_a_name' => $input->playerAName,
            'player_b_name' => $input->playerBName,
            'answered_at' => $input->answeredAt,
        ]);

        MemoryCreated::dispatch(new MemoryCreatedData(
            memoryUlid: $memory->ulid,
            coupleId: $input->coupleId,
            questionUlid: $input->questionUlid,
            answeredAt: $memory->answered_at->toIso8601ZuluString(),
        ));

        return $memory->load('question.category');
    }
}
