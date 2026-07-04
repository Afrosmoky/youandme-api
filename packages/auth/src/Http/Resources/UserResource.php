<?php

namespace Youandme\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Youandme\Auth\Models\User;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'email' => $this->email,
            'nickname' => $this->nickname,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'email_verified_at' => $this->email_verified_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
