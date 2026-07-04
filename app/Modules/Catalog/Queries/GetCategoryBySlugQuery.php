<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Data\CategoryData;
use App\Modules\Catalog\Models\Category;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCategoryBySlugQuery
{
    use AsAction;

    public function handle(string $slug): ?CategoryData
    {
        $category = Category::query()->where('slug', $slug)->first();

        return $category ? CategoryData::fromModel($category) : null;
    }
}
