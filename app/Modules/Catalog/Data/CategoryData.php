<?php

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Models\Category;
use Spatie\LaravelData\Data;

/**
 * Public contract for a category. Note: the /categories HTTP response still
 * serializes via CategoryResource (byte-identical, snake_case + data wrapper);
 * this DTO is the typed Public API returned by Catalog Queries for other modules.
 */
final class CategoryData extends Data
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $tone,
        public readonly bool $premiumOnly,
        public readonly int $ordering,
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
