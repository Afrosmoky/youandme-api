<?php

namespace Youandme\Auth\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;

final class SendVerificationEmailAction
{
    use AsAction;

    /**
     * Re-request the verification email. Returns false (and does nothing) when
     * the user is already verified, true when a request was emitted.
     */
    public function handle(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        // Emits EmailVerificationRequested (see User::sendEmailVerificationNotification);
        // Notifications sends the mail.
        $user->sendEmailVerificationNotification();

        return true;
    }
}
