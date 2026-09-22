<?php

namespace Youandme\Auth\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;

/**
 * Set a user's active couple. users.active_couple_id is an Auth-owned column
 * whose value is a Game couple id — the write crosses the boundary through this
 * action so Game never writes to the users table. The couple id is supplied by
 * the app composition root (from Game\CreateCoupleForUserAction).
 *
 * null clears it: deleting an account has to unhook the user from the couple
 * before Game can delete the couple row (users.active_couple_id → couples has no
 * cascade, and couples.user_a_id points back at users).
 */
final class SetActiveCoupleForUserAction
{
    use AsAction;

    public function handle(User $user, ?int $coupleId): void
    {
        $user->active_couple_id = $coupleId;
        $user->save();
    }
}
