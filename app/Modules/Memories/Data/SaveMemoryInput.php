<?php

namespace App\Modules\Memories\Data;

use DateTimeInterface;
use Spatie\LaravelData\Data;

/**
 * Input for SaveMemoryAction, built by Game::SaveMemoryFromAnswerAction. FK
 * values (coupleId, userId, gameSessionId) cross the boundary as ids (DR-009);
 * the question crosses as a ulid (Memories resolves it via Catalog). Player
 * names are snapshotted by the caller (from user.nickname / couple.partner_name).
 */
final class SaveMemoryInput extends Data
{
    public function __construct(
        public readonly int $coupleId,
        public readonly string $questionUlid,
        public readonly int $userId,
        public readonly ?string $playerAName,
        public readonly ?string $playerBName,
        public readonly string $answerA,
        public readonly ?string $answerB,
        public readonly ?int $gameSessionId,
        public readonly string $origin,
        public readonly DateTimeInterface $answeredAt,
    ) {}
}
