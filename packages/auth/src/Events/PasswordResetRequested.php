<?php

namespace Youandme\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A password reset link was requested. Carries the fully-built (deep-link)
 * reset URL so downstream listeners never need to touch the Auth User model.
 * Consumed by Notifications (Etap 3) to send the reset email.
 */
final readonly class PasswordResetRequested
{
    use Dispatchable;

    public function __construct(
        public string $email,
        public string $resetUrl,
    ) {}
}
