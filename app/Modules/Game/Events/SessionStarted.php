<?php

namespace App\Modules\Game\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A couple started a game session. DTO payload (no Eloquent). No listener in R1.
 */
final readonly class SessionStarted
{
    use Dispatchable;

    public function __construct(
        public string $coupleUlid,
        public string $sessionUlid,
    ) {}
}
