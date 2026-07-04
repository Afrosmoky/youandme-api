<?php

namespace Youandme\Notifications;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Youandme\Auth\Events\EmailVerificationRequested;
use Youandme\Auth\Events\PasswordResetRequested;
use Youandme\Notifications\Listeners\SendPasswordResetLinkOnRequest;
use Youandme\Notifications\Listeners\SendVerificationEmailOnVerificationRequested;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/Views', 'notifications');

        // Notifications consumes Auth events (downstream). Auth never references
        // Notifications — the coupling is one-directional through these events.
        Event::listen(EmailVerificationRequested::class, SendVerificationEmailOnVerificationRequested::class);
        Event::listen(PasswordResetRequested::class, SendPasswordResetLinkOnRequest::class);
    }
}
