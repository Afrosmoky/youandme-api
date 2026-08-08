<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * UNUSED as of S2 — kept, but do not trust it as the contract. Nothing
 * instantiates this class: every question that leaves the API is built by hand,
 * from QuestionData, in Game's QuestionController (/questions/next, /questions/deck)
 * and DailyCardController, or inlined into Memories' MemoryResource. Editing this
 * file changes no response; S2's options went into those payloads, not here.
 *
 * Left in place rather than deleted because the three hand-rolled copies still
 * point at it as the reference shape, and folding them back into one Resource is
 * a refactor of its own, not a side effect of adding a field.
 *
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
