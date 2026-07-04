<?php

use Illuminate\Support\Facades\Mail;
use Youandme\Auth\Events\EmailVerificationRequested;
use Youandme\Auth\Events\PasswordResetRequested;
use Youandme\Notifications\Mail\PasswordResetLink;
use Youandme\Notifications\Mail\VerificationEmail;

test('EmailVerificationRequested triggers exactly one VerificationEmail', function (): void {
    Mail::fake();

    EmailVerificationRequested::dispatch(
        '01hxexampleulid',
        'ola@example.com',
        'https://app.test/api/v1/auth/email/verify/1/abc',
    );

    Mail::assertSent(VerificationEmail::class, 1);
    Mail::assertSent(VerificationEmail::class, fn (VerificationEmail $m): bool => $m->hasTo('ola@example.com')
        && $m->message->subject !== ''
        && $m->message->templateData['url'] === 'https://app.test/api/v1/auth/email/verify/1/abc');
});

test('PasswordResetRequested triggers exactly one PasswordResetLink', function (): void {
    Mail::fake();

    PasswordResetRequested::dispatch(
        'ola@example.com',
        'jaity://reset-password?token=xyz&email=ola%40example.com',
    );

    Mail::assertSent(PasswordResetLink::class, 1);
    Mail::assertSent(PasswordResetLink::class, fn (PasswordResetLink $m): bool => $m->hasTo('ola@example.com')
        && $m->message->templateData['url'] === 'jaity://reset-password?token=xyz&email=ola%40example.com');
});
