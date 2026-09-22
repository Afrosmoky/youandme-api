<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\CoupleWeeklyRitual;
use App\Modules\Game\Models\GameSession;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Delete a couple together with everything Game keeps about it: the played set,
 * likes, unlocked cards, weekly rituals, sessions, and finally the couple row.
 *
 * Game's tables only. The caller runs the other modules first, because their rows
 * point at the couple (or at its sessions) without a cascade: Memories before the
 * sessions go, Progress / Premium / Rewards before the couple does, and Auth has
 * to have cleared users.active_couple_id already.
 */
final class DeleteCoupleAction
{
    use AsAction;

    public function handle(int $coupleId): void
    {
        DB::table('couple_question_seen')->where('couple_id', $coupleId)->delete();
        DB::table('couple_question_likes')->where('couple_id', $coupleId)->delete();
        DB::table('couple_unlocked_questions')->where('couple_id', $coupleId)->delete();
        CoupleWeeklyRitual::query()->where('couple_id', $coupleId)->delete();
        GameSession::query()->where('couple_id', $coupleId)->delete();
        Couple::query()->whereKey($coupleId)->delete();
    }
}
