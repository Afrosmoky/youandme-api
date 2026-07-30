<?php

use Illuminate\Support\Facades\Route;
use Youandme\Notifications\Http\Controllers\DeviceTokenController;

/*
 | Youandme\Notifications package API routes, loaded by
 | NotificationsServiceProvider (the Etap 0 scaffold claimed no route until P7 —
 | that TODO is now closed).
 |
 | Mail needs no endpoint: it is event-driven. Push does — a device has to tell
 | the server where it can be reached, and that address belongs to this package.
 */

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    // Register or refresh this device's push address. Idempotent per token.
    Route::post('device-tokens', [DeviceTokenController::class, 'store']);
});
