<?php

namespace App\Modules\Game\Queries;

use App\Modules\Game\Data\GameSessionData;
use App\Modules\Game\Models\GameSession;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The couple's active (not-yet-ended) session as a DTO, or null. Public read API
 * for other modules; the HTTP `GET /sessions/active` serializes the model via
 * SessionResource instead (byte-identical).
 */
final class GetActiveSessionForCoupleQuery
{
    use AsAction;

    public function handle(int $coupleId): ?GameSessionData
    {
        $session = GameSession::query()
            ->where('couple_id', $coupleId)
            ->active()
            ->first();

        return $session ? GameSessionData::fromModel($session) : null;
    }
}
