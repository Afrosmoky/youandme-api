<?php

use App\Modules\Catalog\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

/*
 | Catalog module API routes. Loaded by CatalogServiceProvider wrapped in the
 | `api` prefix + middleware group, so paths resolve to /api/v1/... exactly as
 | before extraction. Catalog exposes only the read-only categories listing;
 | /questions/next is session flow (Game), not Catalog.
 */

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('categories', [CategoryController::class, 'index']);
});
