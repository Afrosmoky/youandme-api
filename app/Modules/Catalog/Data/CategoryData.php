<?php

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Models\Category;
use Spatie\LaravelData\Data;

/**
 * Public contract for a category. Note: the /categories HTTP response still
 * serializes via CategoryResource (byte-identical, snake_case + data wrapper);
 * this DTO is the typed Public API returned by Catalog Queries for other modules.
 */
class CategoryData extends Data
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?string $description,
        public ?string $tone,
        public bool $premiumOnly,
        public int $ordering,
    ) {}

    public static function fromModel(Category $category): self
    {
        return new self(
            slug: $category->slug,
            name: $category->name,
            description: $category->description,
            tone: $category->tone,
            premiumOnly: (bool) $category->premium_only,
            ordering: (int) $category->ordering,
        );
    }
}
