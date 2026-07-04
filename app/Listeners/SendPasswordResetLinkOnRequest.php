<?php

namespace App\Listeners;

use Youandme\Auth\Events\PasswordResetRequested;
use Youandme\Notifications\Actions\SendPasswordResetLinkAction;

/**
 * Composition-root wiring: maps the Auth event to the Notifications action.
 */
final class SendPasswordResetLinkOnRequest
{
    public function handle(PasswordResetRequested $event): void
    {
        SendPasswordResetLinkAction::run($event->email, $event->resetUrl);
    }
}
