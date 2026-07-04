<?php

namespace App\Modules\Game\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A game session was ended. DTO payload. No listener in R1.
 */
final readonly class SessionEnded
{
    use Dispatchable;

    public function __construct(
        public string $coupleUlid,
        public string $sessionUlid,
    ) {}
}
