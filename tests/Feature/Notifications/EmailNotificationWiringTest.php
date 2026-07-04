<?php

use App\Listeners\SendPasswordResetLinkOnRequest;
use App\Listeners\SendVerificationEmailOnVerificationRequested;
use Illuminate\Support\Facades\Mail;
use Youandme\Auth\Events\EmailVerificationRequested;
use Youandme\Auth\Events\PasswordResetRequested;
use Youandme\Notifications\Mail\PasswordResetLink;
use Youandme\Notifications\Mail\VerificationEmail;

// App composition-root wiring: Auth events → Notifications mails. This is the
// only layer that references both packages.

test('dispatching EmailVerificationRequested sends exactly one VerificationEmail', function (): void {
    Mail::fake();

    EmailVerificationRequested::dispatch('01hxexampleulid', 'ola@example.com', 'https://app.test/verify/1/abc');

    Mail::assertSent(VerificationEmail::class, 1);
    Mail::assertSent(VerificationEmail::class, fn (VerificationEmail $m): bool => $m->hasTo('ola@example.com')
        && $m->message->templateData['url'] === 'https://app.test/verify/1/abc');
});

test('dispatching PasswordResetRequested sends exactly one PasswordResetLink', function (): void {
    Mail::fake();

    PasswordResetRequested::dispatch('ola@example.com', 'jaity://reset-password?token=xyz');

    Mail::assertSent(PasswordResetLink::class, 1);
    Mail::assertSent(PasswordResetLink::class, fn (PasswordResetLink $m): bool => $m->hasTo('ola@example.com')
        && $m->message->templateData['url'] === 'jaity://reset-password?token=xyz');
});

test('verification listener forwards recipient and URL to Notifications', function (): void {
    Mail::fake();

    (new SendVerificationEmailOnVerificationRequested)->handle(
        new EmailVerificationRequested('01hxexampleulid', 'ola@example.com', 'https://verify.test/x'),
    );

    Mail::assertSent(VerificationEmail::class, fn (VerificationEmail $m): bool => $m->hasTo('ola@example.com')
        && $m->message->templateData['url'] === 'https://verify.test/x');
});

test('password reset listener forwards recipient and URL to Notifications', function (): void {
    Mail::fake();

    (new SendPasswordResetLinkOnRequest)->handle(
        new PasswordResetRequested('ola@example.com', 'jaity://reset?x'),
    );

    Mail::assertSent(PasswordResetLink::class, fn (PasswordResetLink $m): bool => $m->hasTo('ola@example.com')
        && $m->message->templateData['url'] === 'jaity://reset?x');
});
