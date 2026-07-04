<?php

namespace App\Modules\Game\Actions;

use App\Events\MemoryCreated;
use App\Models\Memory;
use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Events\QuestionAnswered;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\GameSession;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;

/**
 * Save a memory from a session answer: create the memory, mark the question seen
 * forever, advance the session state, and emit QuestionAnswered. The caller
 * (MemoryController::store) has already resolved the session/question and run the
 * state + race guards.
 *
 * TODO Etap 5 (Memories): the memory write is a bridge (App\Models\Memory +
 * App\Events\MemoryCreated) — replace with Memories\SaveMemoryAction. Do not
 * wire to a Memories Public API yet.
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
        $memory = DB::transaction(function () use ($session, $question, $user, $couple, $answerA, $answerB, $answeredAt): Memory {
            $memory = $couple->memories()->create([
                'user_id' => $user->id,
                'question_id' => $question->id,
                'game_session_id' => $session->id,
                'origin' => 'session',
                'answer_a' => $answerA,
                'answer_b' => $answerB,
                'player_a_name' => $user->nickname,
                'player_b_name' => $couple->partner_name_local,
                'answered_at' => $answeredAt,
            ]);

            $memory->setRelation('question', $question);

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

            return $memory;
        });

        QuestionAnswered::dispatch($couple->ulid, $question->ulid, $memory->ulid);
        MemoryCreated::dispatch($memory);

        return $memory;
    }
}
