<?php

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Models\Ritual;
use Spatie\LaravelData\Data;

/**
 * Public contract for a ritual. Consumed by Game (which holds a ritual_id FK and
 * resolves the content through Catalog, never loading the Ritual model itself).
 */
final class RitualData extends Data
{
    public function __construct(
        public readonly string $ulid,
        public readonly string $title,
        public readonly string $body,
    ) {}

    public static function fromModel(Ritual $ritual): self
    {
        return new self(
            ulid: $ritual->ulid,
            title: $ritual->title,
            body: $ritual->body,
        );
    }
}
