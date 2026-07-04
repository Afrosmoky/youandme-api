<?php

use App\Http\Controllers\Api\V1\MemoryController;
use Illuminate\Support\Facades\Route;

// Auth endpoints live in the youandme/auth package; /categories in Catalog;
// sessions + questions/next in App\Modules\Game — each self-registers its routes
// via its service provider. Only the memories endpoints remain here until
// Memories is extracted (Etap 5).
Route::prefix('v1')->group(function (): void {
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('memories', [MemoryController::class, 'index']);
        Route::post('memories', [MemoryController::class, 'store']);
    });
});
