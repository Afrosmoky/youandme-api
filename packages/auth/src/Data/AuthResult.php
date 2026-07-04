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
class AuthResult extends Data
{
    public function __construct(
        public UserData $user,
        public string $token,
        public bool $isNewUser = false,
    ) {}
}
