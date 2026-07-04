<?php

namespace Youandme\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A password reset link was requested for an email address. Consumed by
 * Notifications from Etap 3 (send the reset link); no listener in Etap 1, where
 * the link is still sent inline via Password::sendResetLink.
 */
class PasswordResetRequested
{
    use Dispatchable;

    public function __construct(
        public readonly string $email,
    ) {}
}
