<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Events\CoupleCreated;
use App\Modules\Game\Models\Couple;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create the solo couple for a newly registered user. Called synchronously from
 * the app composition root at registration (the response must carry the couple).
 * Returns the Eloquent model so the caller can wire users.active_couple_id (via
 * Auth) and serialize the response — see docs r1-architecture-proposal §3.1.
 */
final class CreateCoupleForUserAction
{
    use AsAction;

    public function handle(int $userId): Couple
    {
        $couple = Couple::create(['user_a_id' => $userId]);

        CoupleCreated::dispatch($couple->ulid);

        return $couple;
    }
}
