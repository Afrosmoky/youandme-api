<?php

namespace App\Modules\Memories\Data;

use Spatie\LaravelData\Data;

/**
 * A cursor page of memories — the read Public API shape for
 * ListMemoriesForCoupleQuery. The HTTP index serializes via MemoryResource
 * (byte-identical); this is for cross-module consumers.
 */
final class MemoryPage extends Data
{
    /**
     * @param  list<MemoryData>  $data
     */
    public function __construct(
        public readonly array $data,
        public readonly ?string $nextCursor,
        public readonly ?string $prevCursor,
        public readonly int $perPage,
    ) {}
}
