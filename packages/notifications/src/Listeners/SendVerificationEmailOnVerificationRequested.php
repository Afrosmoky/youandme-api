<?php

namespace Youandme\Notifications\Listeners;

use Youandme\Auth\Events\EmailVerificationRequested;
use Youandme\Notifications\Actions\SendVerificationEmailAction;
use Youandme\Notifications\Data\EmailMessageData;

final class SendVerificationEmailOnVerificationRequested
{
    public function handle(EmailVerificationRequested $event): void
    {
        SendVerificationEmailAction::run(new EmailMessageData(
            recipient: $event->email,
            subject: 'Potwierdź adres e-mail w aplikacji Ja i Ty',
            template: 'notifications::emails.verification',
            templateData: ['url' => $event->verificationUrl],
        ));
    }
}
