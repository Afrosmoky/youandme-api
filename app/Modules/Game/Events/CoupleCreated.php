<?php

namespace App\Modules\Game\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A couple was created (at registration). DTO payload. Notifications may plan a
 * daily-card push in P4; no listener in R1.
 */
final readonly class CoupleCreated
{
    use Dispatchable;

    public function __construct(
        public string $coupleUlid,
    ) {}
}
