<?php

namespace App\Modules\Game\Support;

/**
 * One couple a user belongs to, as seen by account deletion: which couple, and
 * whether somebody else is in it too.
 */
final readonly class CoupleMembership
{
    public function __construct(
        public int $coupleId,
        public bool $isShared,
    ) {}
}
