<?php

namespace Youandme\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A user changed their password (via reset or in-app change). No listener in
 * Etap 1; Notifications may send a confirmation email from Etap 3.
 */
class PasswordChanged
{
    use Dispatchable;

    public function __construct(
        public readonly string $userUlid,
    ) {}
}
