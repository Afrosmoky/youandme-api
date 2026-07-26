<?php

use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Game\GameServiceProvider;
use App\Modules\Memories\MemoriesServiceProvider;
use App\Modules\Rewards\RewardsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\ModuleEventServiceProvider;

return [
    AppServiceProvider::class,
    // Composition root for cross-module / cross-package event wiring (Auth event
    // → Notifications action, etc.). Keeps the packages mutually independent.
    ModuleEventServiceProvider::class,
    // R1 domain modules (app/Modules/*) — registered manually since they are
    // not composer packages and have no auto-discovery. The reusable packages
    // (youandme/auth, youandme/notifications) self-register via package
    // auto-discovery and must NOT be listed here.
    CatalogServiceProvider::class,
    GameServiceProvider::class,
    MemoriesServiceProvider::class,
    RewardsServiceProvider::class,
];
