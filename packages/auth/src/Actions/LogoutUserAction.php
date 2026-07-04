<?php

namespace Youandme\Auth\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;

final class LogoutUserAction
{
    use AsAction;

    /**
     * Revoke the token used for the current request, leaving other devices
     * signed in.
     */
    public function handle(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
