<?php

namespace Youandme\Notifications\Actions;

use Illuminate\Support\Facades\Mail;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Notifications\Data\EmailMessageData;
use Youandme\Notifications\Mail\PasswordResetLink;

/**
 * Sends the password reset email. Notifications fully owns the presentation
 * (subject, template) — callers pass only semantic data (recipient + the reset
 * URL to put in the mail).
 */
final class SendPasswordResetLinkAction
{
    use AsAction;

    public function handle(string $recipient, string $resetUrl): void
    {
        $message = new EmailMessageData(
            recipient: $recipient,
            subject: 'Zresetuj hasło w aplikacji Ja i Ty',
            template: 'notifications::emails.password-reset',
            templateData: ['url' => $resetUrl],
        );

        Mail::to($message->recipient)->send(new PasswordResetLink($message));
    }
}
