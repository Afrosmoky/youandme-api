<?php

namespace Youandme\Notifications\Actions;

use Illuminate\Support\Facades\Mail;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Notifications\Data\EmailMessageData;
use Youandme\Notifications\Mail\PasswordResetLink;

final class SendPasswordResetLinkAction
{
    use AsAction;

    public function handle(EmailMessageData $message): void
    {
        Mail::to($message->recipient)->send(new PasswordResetLink($message));
    }
}
