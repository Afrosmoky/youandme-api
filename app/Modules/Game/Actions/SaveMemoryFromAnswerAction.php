<?php

namespace App\Modules\Game\Actions;

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Events\QuestionAnswered;
use App\Modules\Game\Exceptions\NoActiveSessionException;
use App\Modules\Game\Exceptions\SessionExhaustedException;
use App\Modules\Game\Exceptions\StaleCardException;
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
 * The full guarded save of a session answer, owned by Game (session state is a
 * Game concern). Validates the active session and the current card, persists the
 * memory through Memories\SaveMemoryAction, marks the question seen, advances the
 * session state, and emits QuestionAnswered.
 *
 * Guard failures throw exceptions whose render() reproduces the exact P3 422/409
 * bodies, so the thin app controller stays byte-1:1. Dependency stays one-way:
 * Game → Memories.
 *
 * @throws NoActiveSessionException|SessionExhaustedException|StaleCardException
 */
final class SaveMemoryFromAnswerAction
{
    use AsAction;

    /**
     * @return array{0: Memory, 1: GameSession}
     */
    public function handle(
        User $user,
        string $questionUlid,
        string $answerA,
        ?string $answerB,
        DateTimeInterface $answeredAt,
    ): array {
        $couple = Couple::findOrFail($user->active_couple_id);

        $session = $couple->gameSessions()->active()->first();
        if ($session === null) {
            throw new NoActiveSessionException;
        }

        $state = $session->state;
        $currentIndex = (int) ($state['current_index'] ?? 0);
        /** @var list<int> $remainingIds */
        $remainingIds = $state['remaining_ids'] ?? [];

        if ($currentIndex >= count($remainingIds)) {
            throw new SessionExhaustedException;
        }

        $question = Question::where('ulid', $questionUlid)->firstOrFail();

        // Race guard: the client may have submitted a stale card after a refresh.
        if ($question->id !== $remainingIds[$currentIndex]) {
            throw new StaleCardException;
        }

        return DB::transaction(function () use ($session, $question, $user, $couple, $answerA, $answerB, $answeredAt, $state, $currentIndex): array {
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

            $state['current_index'] = $currentIndex + 1;
            $session->state = $state;
            $session->cards_drawn_count++;
            $session->cards_saved_count++;
            $session->save();

            QuestionAnswered::dispatch($couple->ulid, $question->ulid, $memory->ulid);

            return [$memory, $session];
        });
    }
}
