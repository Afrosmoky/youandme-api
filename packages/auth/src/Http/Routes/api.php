<?php

use Illuminate\Support\Facades\Route;
use Youandme\Auth\Http\Controllers\AuthController;
use Youandme\Auth\Http\Controllers\EmailVerificationController;
use Youandme\Auth\Http\Controllers\PasswordResetController;
use Youandme\Auth\Http\Controllers\ProfileController;
use Youandme\Auth\Http\Controllers\SocialAuthController;

/*
 | Youandme\Auth package API routes. Loaded by AuthServiceProvider wrapped in the
 | `api` prefix + middleware group (matching bootstrap withRouting), so paths
 | resolve to /api/v1/... exactly as before extraction.
 */

Route::prefix('v1')->group(function (): void {
    // Throttle is per IP (Laravel default). Sensitive auth endpoints get tighter
    // limits to blunt brute force and mail spam; tuned per endpoint.
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1');

    Route::post('auth/google', [SocialAuthController::class, 'google'])
        ->middleware('throttle:10,1');
    Route::post('auth/apple', [SocialAuthController::class, 'apple'])
        ->middleware('throttle:10,1');

    Route::post('auth/password/forgot', [PasswordResetController::class, 'forgot'])
        ->middleware('throttle:3,1');
    Route::post('auth/password/reset', [PasswordResetController::class, 'reset']);

    // Clicked from the verification email; protected by the URL signature
    // (no bearer token available at this point), not by auth:sanctum.
    Route::get('auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::post('auth/email/verify-notification', [EmailVerificationController::class, 'notification']);
        Route::get('me/verification-status', [EmailVerificationController::class, 'status']);

        Route::get('me', [ProfileController::class, 'show']);
        Route::patch('me', [ProfileController::class, 'update']);
        Route::post('me/change-password', [ProfileController::class, 'changePassword']);
    });
});
