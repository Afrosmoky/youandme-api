<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MemoryController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SocialAuthController;
use Illuminate\Support\Facades\Route;

// App composition root: endpoints that return a couple (register / login /
// social / GET me / PATCH me) orchestrate Auth + Game here. Couple-free auth
// endpoints live in the youandme/auth package; sessions/questions in Game;
// categories in Catalog. Memories stay here until Etap 5.
Route::prefix('v1')->group(function (): void {
    // Same per-IP throttle limits as before the move.
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1');

    Route::post('auth/google', [SocialAuthController::class, 'google'])
        ->middleware('throttle:10,1');
    Route::post('auth/apple', [SocialAuthController::class, 'apple'])
        ->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [ProfileController::class, 'show']);
        Route::patch('me', [ProfileController::class, 'update']);

        Route::get('memories', [MemoryController::class, 'index']);
        Route::post('memories', [MemoryController::class, 'store']);
    });
});
