<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Thin adapter for the categories listing. The HTTP response serializes via
 * CategoryResource (byte-identical `data`-wrapped, snake_case); ListCategoriesQuery
 * is the DTO Public API for other modules — see docs r1-architecture-proposal §2.2.
 */
final class CategoryController
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->orderBy('ordering')->get()
        );
    }
}
