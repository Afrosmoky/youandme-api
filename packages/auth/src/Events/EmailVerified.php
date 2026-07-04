<?php

namespace Youandme\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A user confirmed their email address. No listener in Etap 1.
 */
final readonly class EmailVerified
{
    use Dispatchable;

    public function __construct(
        public readonly string $userUlid,
    ) {}
}
