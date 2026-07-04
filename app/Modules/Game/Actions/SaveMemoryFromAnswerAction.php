<?php

namespace App\Modules\Game\Actions;

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Events\QuestionAnswered;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\GameSession;
use App\Modules\Memories\Actions\SaveMemoryAction;
use App\Modules\Memories\Data\SaveMemoryInput;
use App\Modules\Memories\Models\Memory;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;

/**
 * Save a memory from a session answer: persist the memory (through Memories),
 * mark the question seen forever, advance the session state, and emit
 * QuestionAnswered. The caller (MemoryController::store) has already resolved the
 * session/question and run the state + race guards.
 *
 * The memory write goes through Memories\SaveMemoryAction (Game depends on the
 * Memories Public API — allowed, both are app-specific modules). Game keeps only
 * its own concerns: the seen pivot and session state.
 */
final class SaveMemoryFromAnswerAction
{
    use AsAction;

    public function handle(
        GameSession $session,
        Question $question,
        User $user,
        Couple $couple,
        string $answerA,
        ?string $answerB,
        DateTimeInterface $answeredAt,
    ): Memory {
        return DB::transaction(function () use ($session, $question, $user, $couple, $answerA, $answerB, $answeredAt): Memory {
            $memory = SaveMemoryAction::run(new SaveMemoryInput(
                coupleId: $couple->id,
                questionUlid: $question->ulid,
                userId: $user->id,
                playerAName: $user->nickname,
                playerBName: $couple->partner_name_local,
                answerA: $answerA,
                answerB: $answerB,
                gameSessionId: $session->id,
                origin: 'session',
                answeredAt: $answeredAt,
            ));

            // Mark seen forever (idempotent on the composite PK) and advance the
            // session: re-assign the whole state array so Eloquent tracks it.
            $couple->seenQuestions()->syncWithoutDetaching([
                $question->id => ['seen_at' => now()],
            ]);

            $state = $session->state;
            $currentIndex = isset($state['current_index']) ? (int) $state['current_index'] : 0;
            $state['current_index'] = $currentIndex + 1;
            $session->state = $state;
            $session->cards_drawn_count++;
            $session->cards_saved_count++;
            $session->save();

            QuestionAnswered::dispatch($couple->ulid, $question->ulid, $memory->ulid);

            return $memory;
        });
    }
}
