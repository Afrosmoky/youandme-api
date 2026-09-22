<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Models\Couple;
use App\Modules\Game\Support\CoupleMembership;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lock every couple a user belongs to, on either side, and say which of them are
 * shared — the first step of deleting an account.
 *
 * FOR UPDATE, so nothing new is attached to these couples while their data is
 * being erased: an insert into a child table takes a key-share lock on the couple
 * row and therefore waits, then fails its foreign key once we commit, instead of
 * landing between our deletes and breaking the final DELETE of the couple.
 *
 * Every couple, not just users.active_couple_id: that column is Auth's shortcut,
 * and a couple it no longer points at would still hold the user's data.
 *
 * Only an Action because of the lock; it writes nothing. Nothing sets user_b_id
 * today (the two-player remote game is still to come), so isShared is always
 * false in practice — the caller refuses rather than guesses what deleting one
 * half of a shared history should mean.
 */
final class LockCouplesOfUserAction
{
    use AsAction;

    /**
     * @return list<CoupleMembership>
     */
    public function handle(int $userId): array
    {
        return Couple::query()
            ->where(fn ($query) => $query->where('user_a_id', $userId)->orWhere('user_b_id', $userId))
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'user_a_id', 'user_b_id'])
            ->map(fn (Couple $couple): CoupleMembership => new CoupleMembership(
                coupleId: $couple->id,
                isShared: $couple->user_b_id !== null,
            ))
            ->values()
            ->all();
    }
}
