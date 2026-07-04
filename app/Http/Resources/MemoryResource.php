<?php

namespace App\Http\Resources;

use App\Models\Memory;
use App\Modules\Catalog\Http\Resources\QuestionResource;
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
            'question' => new QuestionResource($this->whenLoaded('question')),
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
