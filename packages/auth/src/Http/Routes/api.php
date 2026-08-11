<?php

use Illuminate\Support\Facades\Route;
use Youandme\Auth\Http\Controllers\AuthController;
use Youandme\Auth\Http\Controllers\EmailVerificationController;
use Youandme\Auth\Http\Controllers\PasswordResetController;
use Youandme\Auth\Http\Controllers\ProfileController;

/*
 | Youandme\Auth package API routes — couple-free endpoints only. Loaded by
 | AuthServiceProvider wrapped in the `api` prefix + middleware group.
 |
 | register / login / google / apple / GET me / PATCH me return or touch the
 | couple (Game) and are registered in the app layer (routes/api.php).
 */

Route::prefix('v1')->group(function (): void {
    // Throttle is per IP (Laravel default). Sensitive auth endpoints get tighter
    // limits to blunt brute force and mail spam; tuned per endpoint.
    Route::post('auth/password/forgot', [PasswordResetController::class, 'forgot'])
        ->middleware('throttle:3,1');
    Route::post('auth/password/reset', [PasswordResetController::class, 'reset']);

    // Where the reset mail points. Same path as the POST above, different verb:
    // this one renders the page that hands the token to the app, the POST is the
    // reset itself. The mail cannot link jaity:// directly - see the handoff
    // view - so the token travels through here.
    Route::get('auth/password/reset', [PasswordResetController::class, 'open'])
        ->name('password.reset.open');

    // Clicked from the verification email; protected by the URL signature
    // (no bearer token available at this point), not by auth:sanctum.
    Route::get('auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::post('auth/email/verify-notification', [EmailVerificationController::class, 'notification']);
        Route::get('me/verification-status', [EmailVerificationController::class, 'status']);

        Route::post('me/change-password', [ProfileController::class, 'changePassword']);
    });
});
