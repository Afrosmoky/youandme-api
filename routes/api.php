<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\EmailVerificationController;
use App\Http\Controllers\Api\V1\MemoryController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\QuestionController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\SocialAuthController;
use Illuminate\Support\Facades\Route;

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

        Route::get('categories', [CategoryController::class, 'index']);

        Route::post('sessions/start', [SessionController::class, 'start']);
        Route::get('sessions/active', [SessionController::class, 'active']);
        Route::post('sessions/{session:ulid}/end', [SessionController::class, 'end']);
        Route::post('sessions/{session:ulid}/skip-current', [SessionController::class, 'skipCurrent']);

        Route::get('questions/next', [QuestionController::class, 'next']);

        Route::get('memories', [MemoryController::class, 'index']);
        Route::post('memories', [MemoryController::class, 'store']);
    });
});
