<?php

namespace Youandme\Notifications;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Youandme\Notifications\Support\FcmPushSender;
use Youandme\Notifications\Support\PushSenderInterface;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The push channel behind its interface: FCM today, anything else later,
        // and a fake in tests. Same convention as the token verifiers in Auth.
        $this->app->bind(PushSenderInterface::class, FcmPushSender::class);
    }

    public function boot(): void
    {
        // The package owns its presentation (mail templates) and, since P7, its
        // addressing (where a device can be pushed). Cross-package event wiring —
        // which Auth event sends which mail, which Rewards event sends which push
        // — stays in the app composition root (ModuleEventServiceProvider), so
        // Notifications remains independent of both.
        $this->loadViewsFrom(__DIR__.'/Resources/Views', 'notifications');

        // Wrapped in the `api` prefix + middleware group so package routes land at
        // /api/v1/... exactly like the app's own routes/api.php.
        Route::middleware('api')
            ->prefix('api')
            ->group(fn () => $this->loadRoutesFrom(__DIR__.'/Http/Routes/api.php'));
    }
}
