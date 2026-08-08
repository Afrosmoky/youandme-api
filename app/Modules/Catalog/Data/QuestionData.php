<?php

namespace App\Modules\Catalog\Data;

use App\Modules\Catalog\Models\Question;
use Spatie\LaravelData\Data;

/**
 * Public contract for a question. The nested category is nullable (a question
 * may have no category). Consumed by Game/Memories via Catalog Queries.
 *
 * options (S2) is null for a question answered in the couple's own words, and
 * the envelope {items, multiple} for one answered by picking. It is served as
 * stored, without flattening: "what may be picked" and "how many" are one fact,
 * and splitting them here would only invite a client to read one without the
 * other. The answer itself stays plain text either way, so nothing past Catalog
 * has to learn a new shape.
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
     * @param  array{items: list<string>, multiple: bool}|null  $options
     */
    public function __construct(
        public readonly string $ulid,
        public readonly string $body,
        public readonly string $type,
        public readonly ?CategoryData $category,
        public readonly array $tags,
        public readonly ?array $options,
        public readonly bool $isLocked,
    ) {}

    public static function fromModel(Question $question): self
    {
        /** @var array{items: list<string>, multiple: bool}|null $options */
        $options = $question->options;

        return new self(
            ulid: $question->ulid,
            body: $question->body,
            type: $question->type,
            category: $question->category ? CategoryData::fromModel($question->category) : null,
            tags: $question->tags ?? [],
            options: $options,
            isLocked: (bool) $question->is_locked,
        );
    }
}
