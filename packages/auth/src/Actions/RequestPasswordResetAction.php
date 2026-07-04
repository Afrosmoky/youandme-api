<?php

namespace Youandme\Auth\Actions;

use Illuminate\Support\Facades\Password;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Events\PasswordResetRequested;

class RequestPasswordResetAction
{
    use AsAction;

    /**
     * Trigger a password reset link. Returns the broker status string; the
     * caller always responds 200 regardless (do not leak which emails exist).
     */
    public function handle(string $email): string
    {
        // TODO Etap 3 (Notifications): zastąpić inline send listenerem na
        // PasswordResetRequested → Notifications\SendPasswordResetLinkAction.
        $status = Password::sendResetLink(['email' => $email]);

        PasswordResetRequested::dispatch($email);

        return $status;
    }
}
