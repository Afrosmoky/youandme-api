<?php

namespace Youandme\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Youandme\Auth\Data\UserRegisteredData;

/**
 * A new user account was created (email/password or social). DTO payload.
 * Consumed by Notifications from Etap 3 (welcome + verification email); no
 * listener in Etap 1.
 */
class UserRegistered
{
    use Dispatchable;

    public function __construct(
        public readonly UserRegisteredData $data,
    ) {}
}
