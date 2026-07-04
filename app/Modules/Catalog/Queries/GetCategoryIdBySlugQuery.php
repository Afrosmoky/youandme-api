<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Category;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Resolve a category slug to its internal id, for other modules that hold a
 * category FK (e.g. game_sessions.category_id). This is the physical-FK layer
 * (DR-009); logical reads use CategoryData via ListCategoriesQuery /
 * GetCategoryBySlugQuery.
 */
final class GetCategoryIdBySlugQuery
{
    use AsAction;

    public function handle(string $slug): ?int
    {
        return Category::query()->where('slug', $slug)->value('id');
    }
}
