<?php

namespace Youandme\Auth\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;

/**
 * Mark that a user has opened the app, setting users.first_opened_at once. The
 * write is an atomic conditional UPDATE (whereNull), so two concurrent first
 * requests cannot both "win" — exactly one gets affected == 1. Returns true only
 * for that first call, which the app layer uses as its first-open trigger.
 *
 * Writing to users is Auth's job (like SetActiveCoupleForUserAction) — the caller
 * does the cheap in-memory null check before invoking this, so the UPDATE never
 * runs once the marker is set.
 */
final class MarkFirstOpenedAction
{
    use AsAction;

    public function handle(User $user): bool
    {
        $affected = User::query()
            ->whereKey($user->getKey())
            ->whereNull('first_opened_at')
            ->update(['first_opened_at' => now()]);

        return $affected === 1;
    }
}
