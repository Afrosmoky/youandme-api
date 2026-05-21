<?php

namespace App\Http\Resources;

use App\Models\Memory;
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
            'answer' => $this->answer,
            'answered_at' => $this->answered_at->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
