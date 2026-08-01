<?php

use App\Modules\Memories\Events\MemoryCreated;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Structural guard. Both writers (session answer, daily card) save inside a
 * transaction, so without this contract listeners would run INSIDE it: a failing
 * side effect could roll the answer back, and a listener could act on a memory
 * that never landed. Dropping the interface would reintroduce exactly that, and
 * silently — the milestone tests would still pass.
 */
test('MemoryCreated is held until the transaction commits', function (): void {
    expect(new ReflectionClass(MemoryCreated::class))
        ->implementsInterface(ShouldDispatchAfterCommit::class)
        ->toBeTrue();
});
