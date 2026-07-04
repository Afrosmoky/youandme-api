<?php

namespace Youandme\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A verification email was requested for a user (on registration and on resend).
 * Carries the fully-built signed verification URL so downstream listeners never
 * need to touch the Auth User model. Consumed by Notifications (Etap 3).
 *
 * Separate from UserRegistered on purpose: UserRegistered is a domain fact for
 * any future consumer, this one means "send the verification mail" and fires in
 * both the register and resend paths.
 */
final readonly class EmailVerificationRequested
{
    use Dispatchable;

    public function __construct(
        public string $userUlid,
        public string $email,
        public string $verificationUrl,
    ) {}
}
