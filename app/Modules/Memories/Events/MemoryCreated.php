<?php

namespace App\Modules\Memories\Events;

use App\Modules\Memories\Data\MemoryCreatedData;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A memory was saved. DTO payload. Existed in P3 (App\Events) carrying the
 * Eloquent model; in R1 it lives in the module with a DTO payload. First real
 * consumer in P8 (Progress milestones).
 *
 * ShouldDispatchAfterCommit, added with that first consumer: both writers save
 * inside a transaction (session answer, daily card), so listeners used to run
 * INSIDE it — a failing side effect could have rolled back the answer itself, and
 * a listener could have acted on a memory that never landed. Holding the event
 * until commit restores the project rule "events after the transaction, not
 * inside it" (CLAUDE.md, P3) that this dispatch had drifted from.
 *
 * Externally invisible when introduced: the event had no listener at the time.
 * With no transaction open it still dispatches immediately, so the direct-call
 * path is unchanged.
 */
final readonly class MemoryCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public MemoryCreatedData $data,
    ) {}
}
