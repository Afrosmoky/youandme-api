<?php

namespace Youandme\Auth\Data;

use Spatie\LaravelData\Data;

/**
 * Output of registration / login / social sign-in: the user contract plus the
 * issued Sanctum token.
 *
 * `isNewUser` extends the doc's UserData+token contract minimally so social
 * sign-in (which registers or logs in through one endpoint) can drive the
 * 201-vs-200 status without a second lookup. False for plain login.
 */
final class AuthResult extends Data
{
    public function __construct(
        public readonly UserData $user,
        public readonly string $token,
        public readonly bool $isNewUser = false,
    ) {}
}
