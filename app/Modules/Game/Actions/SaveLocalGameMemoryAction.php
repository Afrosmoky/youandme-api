<?php

namespace App\Modules\Game\Actions;

use App\Modules\Catalog\Queries\GetQuestionIdsByUlidsQuery;
use App\Modules\Game\Exceptions\QuestionNotPlayableException;
use App\Modules\Game\Models\Couple;
use App\Modules\Memories\Actions\SaveMemoryAction;
use App\Modules\Memories\Data\SaveMemoryInput;
use App\Modules\Memories\Models\Memory;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;

/**
 * Save an answer written during a local game (P10). Owned by Game for the same
 * reason the session and daily saves are: writing a memory is Memories' job, but
 * deciding that a card was legitimately played is not.
 *
 * Why the session save could not be reused: it requires an active GameSession and
 * checks the submitted card against that session's current index. A local game
 * has neither — it is sequenced on the phone, and the server holds no state for
 * it at all. Bending those guards to allow "no session" would have left the
 * session flow with a hole in exactly the place the guards exist for.
 *
 * What replaces them is the eligibility check: the card must be one this couple
 * may play (a session card, free or unlocked). It is the same rule the deck
 * endpoint and the batch report apply, through the same Catalog query — the
 * server's answer to "what may be played" has one implementation.
 *
 * player_b_name comes from the caller, not from couple.partner_name_local. In
 * local play the second player is whoever typed their name on the setup screen,
 * and a snapshot has to record who actually answered (the P5/P9 snapshot rule).
 *
 * The memory and the played card land in one transaction, and the card goes
 * through the LIVE door: saving an answer means the card was played, so
 * CardsPlayed fires and the progress map moves. If the batch report later names
 * the same card — and it will, because the phone reports everything it dealt —
 * the set absorbs it without counting twice.
 *
 * @throws QuestionNotPlayableException
 */
final class SaveLocalGameMemoryAction
{
    use AsAction;

    public function handle(
        User $user,
        string $questionUlid,
        string $answerA,
        ?string $answerB,
        string $playerBName,
        DateTimeInterface $answeredAt,
    ): Memory {
        $couple = Couple::findOrFail($user->active_couple_id);

        /** @var list<int> $unlockedIds */
        $unlockedIds = $couple->unlockedQuestions()->pluck('questions.id')->all();

        $playableIds = GetQuestionIdsByUlidsQuery::run([$questionUlid], $unlockedIds);

        if ($playableIds === []) {
            throw new QuestionNotPlayableException;
        }

        return DB::transaction(function () use (
            $couple,
            $user,
            $questionUlid,
            $playableIds,
            $answerA,
            $answerB,
            $playerBName,
            $answeredAt,
        ): Memory {
            $memory = SaveMemoryAction::run(new SaveMemoryInput(
                coupleId: $couple->id,
                questionUlid: $questionUlid,
                userId: $user->id,
                playerAName: $user->nickname,
                playerBName: $playerBName,
                answerA: $answerA,
                answerB: $answerB,
                gameSessionId: null,
                origin: 'local_game',
                answeredAt: $answeredAt,
            ));

            MarkQuestionsPlayedAction::run($couple, $playableIds);

            return $memory;
        });
    }
}
