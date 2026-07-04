<?php

namespace App\Modules\Memories\Data;

/**
 * Payload for the MemoryCreated event. Consumers (Notifications P9 anniversary,
 * Progress P8 milestones, Streak P4) arrive later — no listener in R1.
 */
final readonly class MemoryCreatedData
{
    public function __construct(
        public string $memoryUlid,
        public int $coupleId,
        public string $questionUlid,
        public string $answeredAt,
    ) {}
}
