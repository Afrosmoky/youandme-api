<?php

namespace App\Modules\Game\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A question was answered (a memory saved from a session card). Existed in P3
 * carrying the Memory model; in R1 it carries a DTO payload. No listener in R1.
 */
final readonly class QuestionAnswered
{
    use Dispatchable;

    public function __construct(
        public string $coupleUlid,
        public string $questionUlid,
        public string $memoryUlid,
    ) {}
}
