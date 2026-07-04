<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Events\SessionEnded;
use App\Modules\Game\Models\GameSession;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * End a session (set ended_at). Caller guards against an already-ended session.
 */
final class EndGameSessionAction
{
    use AsAction;

    public function handle(GameSession $session): void
    {
        $session->ended_at = now();
        $session->save();

        SessionEnded::dispatch($session->couple->ulid, $session->ulid);
    }
}
