<?php

namespace App\Providers;

use App\Listeners\CheckMilestonesOnCardsPlayed;
use App\Listeners\SendAdRewardPushOnGrant;
use App\Listeners\SendPasswordResetLinkOnRequest;
use App\Listeners\SendVerificationEmailOnVerificationRequested;
use App\Modules\Game\Events\CardsPlayed;
use App\Modules\Rewards\Events\AdRewardGranted;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as FrameworkEventServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Youandme\Auth\Events\EmailVerificationRequested;
use Youandme\Auth\Events\PasswordResetRequested;

/**
 * The single place for cross-module / cross-package event wiring. The app is the
 * composition root — it may depend on every package, while the packages stay
 * mutually independent. Game→Notifications wiring (P4/P9) will be added here too.
 *
 * Event auto-discovery of app/Listeners is disabled so this provider is the one
 * authoritative registry (explicit over magic, R1 philosophy) — otherwise a
 * listener would be registered twice (discovery + here) and fire twice.
 */
class ModuleEventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Runs before the framework's EventServiceProvider discovers listeners
        // (which happens on booting), so discovery sees the flag as false.
        FrameworkEventServiceProvider::disableEventDiscovery();
    }

    public function boot(): void
    {
        Event::listen(EmailVerificationRequested::class, SendVerificationEmailOnVerificationRequested::class);
        Event::listen(PasswordResetRequested::class, SendPasswordResetLinkOnRequest::class);

        // Rewards → Notifications (P7): with SSV the server, not the client,
        // learns about an ad reward, so the user is told by push.
        Event::listen(AdRewardGranted::class, SendAdRewardPushOnGrant::class);

        // Game → Progress (P8, re-sourced in P10): played cards may advance the
        // progress map. The first consumer of a DOMAIN event here — unlike the
        // notification wirings above, both sides are app modules, and neither
        // knows the other.
        //
        // It hung on Memories\MemoryCreated until P10, when "played" stopped
        // meaning "saved": the local game plays cards it never writes down, and a
        // skip always did. One source, not two — the old wiring is gone rather
        // than kept alongside.
        Event::listen(CardsPlayed::class, CheckMilestonesOnCardsPlayed::class);
    }
}
