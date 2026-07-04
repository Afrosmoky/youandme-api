<?php

namespace Youandme\Auth\Actions;

use Illuminate\Support\Facades\Password;
use Lorisleiva\Actions\Concerns\AsAction;

final class RequestPasswordResetAction
{
    use AsAction;

    /**
     * Trigger a password reset link. Returns the broker status string; the
     * caller always responds 200 regardless (do not leak which emails exist).
     *
     * Password::sendResetLink handles user lookup, broker throttle and token
     * creation, then calls User::sendPasswordResetNotification — which (R1 Etap 3)
     * emits PasswordResetRequested with the reset URL instead of sending a
     * notification. Notifications listens and sends the mail; Auth never calls
     * Notifications.
     */
    public function handle(string $email): string
    {
        return Password::sendResetLink(['email' => $email]);
    }
}
