<?php

namespace App\Http\Resources;

use App\Models\Couple;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Couple
 */
class CoupleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            // user_a_id / user_b_id are an implementation detail, not exposed.
            'partner_name_local' => $this->partner_name_local,
            'streak_current' => $this->streak_current,
            'streak_longest' => $this->streak_longest,
            'daily_push_hour' => $this->daily_push_hour,
            'relationship_started_on' => $this->relationship_started_on?->format('Y-m-d'),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
