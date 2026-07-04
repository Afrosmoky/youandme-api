<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Question
 */
class QuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'body' => $this->body,
            'type' => $this->type,
            'category' => $this->category ? [
                'slug' => $this->category->slug,
                'name' => $this->category->name,
            ] : null,
            'tags' => $this->tags ?? [],
        ];
    }
}
