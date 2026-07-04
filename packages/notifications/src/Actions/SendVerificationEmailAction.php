<?php

namespace Youandme\Notifications\Actions;

use Illuminate\Support\Facades\Mail;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Notifications\Data\EmailMessageData;
use Youandme\Notifications\Mail\VerificationEmail;

final class SendVerificationEmailAction
{
    use AsAction;

    public function handle(EmailMessageData $message): void
    {
        Mail::to($message->recipient)->send(new VerificationEmail($message));
    }
}
