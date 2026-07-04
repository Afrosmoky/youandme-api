<?php

use App\Modules\Memories\Http\Controllers\MemoryController;
use Illuminate\Support\Facades\Route;

/*
 | Memories module API routes. Loaded by MemoriesServiceProvider wrapped in the
 | `api` prefix + middleware group, so paths resolve to /api/v1/... exactly as
 | before extraction.
 */

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('memories', [MemoryController::class, 'index']);
    Route::post('memories', [MemoryController::class, 'store']);
});
