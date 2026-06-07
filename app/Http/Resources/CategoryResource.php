<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // slug is the public identifier; categories have no ulid.
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'tone' => $this->tone,
            'premium_only' => $this->premium_only,
            'ordering' => $this->ordering,
        ];
    }
}
