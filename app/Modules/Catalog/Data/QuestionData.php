<?php

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Models\Question;
use Spatie\LaravelData\Data;

/**
 * Public contract for a question. The nested category is nullable (a question
 * may have no category). Consumed by Game/Memories via Catalog Queries.
 */
final class QuestionData extends Data
{
    /**
     * @param  string[]  $tags
     */
    public function __construct(
        public readonly string $ulid,
        public readonly string $body,
        public readonly string $type,
        public readonly ?CategoryData $category,
        public readonly array $tags,
    ) {}

    public static function fromModel(Question $question): self
    {
        return new self(
            ulid: $question->ulid,
            body: $question->body,
            type: $question->type,
            category: $question->category ? CategoryData::fromModel($question->category) : null,
            tags: $question->tags ?? [],
        );
    }
}
