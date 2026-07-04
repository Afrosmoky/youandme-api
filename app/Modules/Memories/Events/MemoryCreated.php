<?php

namespace App\Modules\Memories\Events;

use App\Modules\Memories\Data\MemoryCreatedData;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A memory was saved. DTO payload. Existed in P3 (App\Events) carrying the
 * Eloquent model; in R1 it lives in the module with a DTO payload. No listener
 * in R1 (Notifications/Progress/Streak consume it in P4/P8/P9).
 */
final readonly class MemoryCreated
{
    use Dispatchable;

    public function __construct(
        public MemoryCreatedData $data,
    ) {}
}
