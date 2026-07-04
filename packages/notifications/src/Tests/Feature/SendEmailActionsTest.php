<?php

use Illuminate\Support\Facades\Mail;
use Youandme\Notifications\Actions\SendPasswordResetLinkAction;
use Youandme\Notifications\Actions\SendVerificationEmailAction;
use Youandme\Notifications\Mail\PasswordResetLink;
use Youandme\Notifications\Mail\VerificationEmail;

// Pure package tests: the Actions own subject + template and take only semantic
// data. No reference to Auth (or any other package).

test('SendVerificationEmailAction sends one VerificationEmail with its own subject/template', function (): void {
    Mail::fake();

    SendVerificationEmailAction::run('ola@example.com', 'https://app.test/verify/1/abc');

    Mail::assertSent(VerificationEmail::class, 1);
    Mail::assertSent(VerificationEmail::class, fn (VerificationEmail $m): bool => $m->hasTo('ola@example.com')
        && $m->message->subject !== ''
        && $m->message->template === 'notifications::emails.verification'
        && $m->message->templateData['url'] === 'https://app.test/verify/1/abc');
});

test('SendPasswordResetLinkAction sends one PasswordResetLink with its own subject/template', function (): void {
    Mail::fake();

    SendPasswordResetLinkAction::run('ola@example.com', 'jaity://reset-password?token=xyz');

    Mail::assertSent(PasswordResetLink::class, 1);
    Mail::assertSent(PasswordResetLink::class, fn (PasswordResetLink $m): bool => $m->hasTo('ola@example.com')
        && $m->message->subject !== ''
        && $m->message->template === 'notifications::emails.password-reset'
        && $m->message->templateData['url'] === 'jaity://reset-password?token=xyz');
});
