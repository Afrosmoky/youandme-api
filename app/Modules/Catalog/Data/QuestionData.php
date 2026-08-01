<?php

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Models\Question;
use Spatie\LaravelData\Data;

/**
 * Public contract for a question. The nested category is nullable (a question
 * may have no category). Consumed by Game/Memories via Catalog Queries.
 *
 * isLocked belongs here because "is this card part of the closed deck" is a
 * property of the CONTENT, which Catalog owns — the per-couple half of the story
 * ("did they unlock it") stays in Game. P7 first left it out on the grounds that
 * a served card is playable by definition; it went in demand-driven once mobile
 * needed to badge unlocked cards inside a session.
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
        public readonly bool $isLocked,
    ) {}

    public static function fromModel(Question $question): self
    {
        return new self(
            ulid: $question->ulid,
            body: $question->body,
            type: $question->type,
            category: $question->category ? CategoryData::fromModel($question->category) : null,
            tags: $question->tags ?? [],
            isLocked: (bool) $question->is_locked,
        );
    }
}
