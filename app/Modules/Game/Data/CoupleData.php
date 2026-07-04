<?php

namespace App\Modules\Game\Data;

use App\Modules\Game\Models\Couple;
use Spatie\LaravelData\Data;

/**
 * Public contract for a couple across module boundaries. HTTP responses still
 * serialize via CoupleResource (byte-identical); this DTO is the typed read API
 * returned by Game Queries for other modules.
 *
 * `final` + readonly properties (not `final readonly class`): a spatie Data
 * subclass cannot be a readonly class.
 */
final class CoupleData extends Data
{
    public function __construct(
        public readonly string $ulid,
        public readonly ?string $partnerNameLocal,
        public readonly int $streakCurrent,
        public readonly int $streakLongest,
        public readonly int $dailyPushHour,
        public readonly ?string $relationshipStartedOn,
        public readonly string $createdAt,
    ) {}

    public static function fromModel(Couple $couple): self
    {
        return new self(
            ulid: $couple->ulid,
            partnerNameLocal: $couple->partner_name_local,
            streakCurrent: (int) $couple->streak_current,
            streakLongest: (int) $couple->streak_longest,
            dailyPushHour: (int) $couple->daily_push_hour,
            relationshipStartedOn: $couple->relationship_started_on?->format('Y-m-d'),
            createdAt: $couple->created_at->toIso8601ZuluString(),
        );
    }
}
