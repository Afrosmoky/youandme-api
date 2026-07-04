<?php

use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Game\GameServiceProvider;
use App\Modules\Memories\MemoriesServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    // R1 domain modules (app/Modules/*) — registered manually since they are
    // not composer packages and have no auto-discovery. The reusable packages
    // (youandme/auth, youandme/notifications) self-register via package
    // auto-discovery and must NOT be listed here.
    CatalogServiceProvider::class,
    GameServiceProvider::class,
    MemoriesServiceProvider::class,
];
