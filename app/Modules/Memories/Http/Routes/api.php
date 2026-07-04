<?php

use App\Modules\Memories\Http\Controllers\MemoryController;
use Illuminate\Support\Facades\Route;

/*
 | Memories module API routes — the pure read list only. POST /memories (the
 | session-answer save, which peer-combines memory + session) is registered in
 | the app layer (routes/api.php).
 */

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('memories', [MemoryController::class, 'index']);
});
