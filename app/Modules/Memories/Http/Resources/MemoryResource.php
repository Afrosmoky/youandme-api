<?php

namespace App\Modules\Memories\Http\Resources;

use App\Modules\Memories\Models\Memory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Memory
 */
class MemoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            // Self-sufficient: reproduce the Catalog question shape (trimmed
            // category) from the eager-loaded relation, byte-1:1 with P3 — without
            // embedding Catalog's QuestionResource.
            'question' => $this->question ? [
                'ulid' => $this->question->ulid,
                'body' => $this->question->body,
                'type' => $this->question->type,
                'category' => $this->question->category ? [
                    'slug' => $this->question->category->slug,
                    'name' => $this->question->category->name,
                ] : null,
                'tags' => $this->question->tags ?? [],
            ] : null,
            'origin' => $this->origin,
            'answer_a' => $this->answer_a,
            'answer_b' => $this->answer_b,
            'player_a_name' => $this->player_a_name,
            'player_b_name' => $this->player_b_name,
            'answered_at' => $this->answered_at->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
