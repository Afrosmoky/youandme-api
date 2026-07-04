<?php

namespace Youandme\Auth\Data;

use Spatie\LaravelData\Data;
use Youandme\Auth\Models\User;

/**
 * Payload for the UserRegistered event. DTO (not the Eloquent model) so
 * listeners in other modules depend on a stable contract, not on Auth internals.
 */
final class UserRegisteredData extends Data
{
    public function __construct(
        public readonly string $userUlid,
        public readonly string $email,
        public readonly string $nickname,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            userUlid: $user->ulid,
            email: $user->email,
            nickname: $user->nickname,
        );
    }
}
