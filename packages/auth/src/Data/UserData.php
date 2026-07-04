<?php

namespace Youandme\Auth\Data;

use Spatie\LaravelData\Data;
use Youandme\Auth\Models\User;

/**
 * Public contract for a user across module boundaries. Dates are ISO 8601 UTC
 * (Zulu) strings, matching UserResource. Note: HTTP responses in Etap 1 still
 * serialize via UserResource (byte-identical); this DTO is the typed Public API
 * returned by Queries and consumed by other modules / event payloads.
 */
class UserData extends Data
{
    public function __construct(
        public string $ulid,
        public string $email,
        public string $nickname,
        public string $timezone,
        public string $locale,
        public ?string $emailVerifiedAt,
        public string $createdAt,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            ulid: $user->ulid,
            email: $user->email,
            nickname: $user->nickname,
            timezone: $user->timezone,
            locale: $user->locale,
            emailVerifiedAt: $user->email_verified_at?->toIso8601ZuluString(),
            createdAt: $user->created_at->toIso8601ZuluString(),
        );
    }
}
