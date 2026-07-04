<?php

namespace Youandme\Notifications\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Youandme\Notifications\Data\EmailMessageData;

final class VerificationEmail extends Mailable
{
    public function __construct(
        public readonly EmailMessageData $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->message->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: $this->message->template,
            with: $this->message->templateData,
        );
    }
}
