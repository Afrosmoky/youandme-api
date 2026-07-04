<?php

namespace Youandme\Notifications;

use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // The package owns only its presentation (mail templates). Cross-package
        // event wiring (which Auth event triggers which mail) lives in the app
        // composition root — see App\Providers\ModuleEventServiceProvider — so
        // Notifications stays independent of Auth.
        $this->loadViewsFrom(__DIR__.'/Resources/Views', 'notifications');
    }
}
