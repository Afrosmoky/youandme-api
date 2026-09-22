<?php

namespace App\Modules\Game\Queries;

use App\Modules\Game\Models\Couple;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Whether the user is in a couple somebody else is in too, on either side.
 *
 * Account deletion asks this before it calls out to Apple, so a deletion that is
 * going to be refused revokes nothing. LockCouplesOfUserAction asks again under
 * the lock; this read only keeps the early answer honest.
 */
final class IsUserInSharedCoupleQuery
{
    use AsAction;

    public function handle(int $userId): bool
    {
        return Couple::query()
            ->whereNotNull('user_b_id')
            ->where(fn ($query) => $query->where('user_a_id', $userId)->orWhere('user_b_id', $userId))
            ->exists();
    }
}
