<?php

namespace App\Modules\Memories\Data;

use App\Modules\Catalog\Data\QuestionData;
use App\Modules\Memories\Models\Memory;
use Spatie\LaravelData\Data;

/**
 * Public contract for a memory. The question is Catalog's QuestionData (the
 * module is self-sufficient — it resolves the question through Catalog's Public
 * API, not by embedding Catalog's QuestionResource). HTTP responses still
 * serialize via MemoryResource (byte-identical, category trimmed to slug+name).
 */
final class MemoryData extends Data
{
    public function __construct(
        public readonly string $ulid,
        public readonly ?QuestionData $question,
        public readonly string $origin,
        public readonly string $answerA,
        public readonly ?string $answerB,
        public readonly ?string $playerAName,
        public readonly ?string $playerBName,
        public readonly string $answeredAt,
        public readonly string $createdAt,
    ) {}

    public static function fromModel(Memory $memory): self
    {
        return new self(
            ulid: $memory->ulid,
            question: $memory->question ? QuestionData::fromModel($memory->question) : null,
            origin: $memory->origin,
            answerA: $memory->answer_a,
            answerB: $memory->answer_b,
            playerAName: $memory->player_a_name,
            playerBName: $memory->player_b_name,
            answeredAt: $memory->answered_at->toIso8601ZuluString(),
            createdAt: $memory->created_at->toIso8601ZuluString(),
        );
    }
}
