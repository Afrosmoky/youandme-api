<?php

namespace App\Listeners;

use Youandme\Auth\Events\EmailVerificationRequested;
use Youandme\Notifications\Actions\SendVerificationEmailAction;

/**
 * Composition-root wiring: maps the Auth event to the Notifications action. This
 * is the only layer that knows both packages — Auth and Notifications stay
 * mutually independent.
 */
final class SendVerificationEmailOnVerificationRequested
{
    public function handle(EmailVerificationRequested $event): void
    {
        SendVerificationEmailAction::run($event->email, $event->verificationUrl);
    }
}
