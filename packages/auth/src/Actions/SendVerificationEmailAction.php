<?php

namespace Youandme\Auth\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;

class SendVerificationEmailAction
{
    use AsAction;

    /**
     * Re-send the verification email. Returns false (and does nothing) when the
     * user is already verified, true when a message was queued.
     */
    public function handle(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        // TODO Etap 3 (Notifications): zastąpić Notifications\SendEmailVerificationLinkAction
        $user->sendEmailVerificationNotification();

        return true;
    }
}
