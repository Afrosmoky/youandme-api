<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Data\CategoryData;
use App\Modules\Catalog\Models\Category;
use Lorisleiva\Actions\Concerns\AsAction;

final class ListCategoriesQuery
{
    use AsAction;

    /**
     * All categories ordered by `ordering`.
     *
     * @return list<CategoryData>
     */
    public function handle(): array
    {
        return Category::query()
            ->orderBy('ordering')
            ->get()
            ->map(fn (Category $category): CategoryData => CategoryData::fromModel($category))
            ->all();
    }
}
