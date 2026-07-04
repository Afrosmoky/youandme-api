<?php

use Illuminate\Support\Facades\Mail;
use Youandme\Auth\Events\EmailVerificationRequested;
use Youandme\Auth\Events\PasswordResetRequested;
use Youandme\Notifications\Listeners\SendPasswordResetLinkOnRequest;
use Youandme\Notifications\Listeners\SendVerificationEmailOnVerificationRequested;
use Youandme\Notifications\Mail\PasswordResetLink;
use Youandme\Notifications\Mail\VerificationEmail;

// Actions are final (coding standards R1) so we don't Mockery-mock them; instead
// we fake Mail and assert the EmailMessageData the listener built reaches the mail.

test('verification listener builds the right EmailMessageData', function (): void {
    Mail::fake();

    (new SendVerificationEmailOnVerificationRequested)->handle(
        new EmailVerificationRequested('01hxexampleulid', 'ola@example.com', 'https://verify.test/x'),
    );

    Mail::assertSent(VerificationEmail::class, fn (VerificationEmail $m): bool => $m->hasTo('ola@example.com')
        && $m->message->template === 'notifications::emails.verification'
        && $m->message->subject !== ''
        && $m->message->templateData['url'] === 'https://verify.test/x');
});

test('password reset listener builds the right EmailMessageData', function (): void {
    Mail::fake();

    (new SendPasswordResetLinkOnRequest)->handle(
        new PasswordResetRequested('ola@example.com', 'jaity://reset?x'),
    );

    Mail::assertSent(PasswordResetLink::class, fn (PasswordResetLink $m): bool => $m->hasTo('ola@example.com')
        && $m->message->template === 'notifications::emails.password-reset'
        && $m->message->subject !== ''
        && $m->message->templateData['url'] === 'jaity://reset?x');
});
