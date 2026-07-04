<?php

namespace Youandme\Notifications\Actions;

use Illuminate\Support\Facades\Mail;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Notifications\Data\EmailMessageData;
use Youandme\Notifications\Mail\VerificationEmail;

/**
 * Sends the verification email. Notifications fully owns the presentation
 * (subject, template) — callers pass only semantic data (recipient + the URL to
 * put in the mail), so nothing outside this package knows the template names.
 */
final class SendVerificationEmailAction
{
    use AsAction;

    public function handle(string $recipient, string $verificationUrl): void
    {
        $message = new EmailMessageData(
            recipient: $recipient,
            subject: 'Potwierdź adres e-mail w aplikacji Ja i Ty',
            template: 'notifications::emails.verification',
            templateData: ['url' => $verificationUrl],
        );

        Mail::to($message->recipient)->send(new VerificationEmail($message));
    }
}
