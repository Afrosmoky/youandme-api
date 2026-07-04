<?php

namespace Youandme\Notifications\Listeners;

use Youandme\Auth\Events\PasswordResetRequested;
use Youandme\Notifications\Actions\SendPasswordResetLinkAction;
use Youandme\Notifications\Data\EmailMessageData;

final class SendPasswordResetLinkOnRequest
{
    public function handle(PasswordResetRequested $event): void
    {
        SendPasswordResetLinkAction::run(new EmailMessageData(
            recipient: $event->email,
            subject: 'Zresetuj hasło w aplikacji Ja i Ty',
            template: 'notifications::emails.password-reset',
            templateData: ['url' => $event->resetUrl],
        ));
    }
}
