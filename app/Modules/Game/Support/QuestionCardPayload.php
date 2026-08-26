<?php

namespace App\Modules\Game\Support;

use App\Modules\Catalog\Data\QuestionData;

/**
 * The card shape every Game endpoint that serves a playable question answers
 * with: the Catalog QuestionResource fields (byte-1:1 with P3) plus the couple's
 * like state, which is a Game concern and not part of QuestionData.
 *
 * It lived as a private method on QuestionController while /questions/next and
 * /questions/deck were the only two callers. The liked list is the third, and a
 * third copy of the key order is exactly how two shapes start drifting — so the
 * builder moved out here, unchanged. A plain static helper, not a Data object:
 * it produces the array the response is built from and never crosses a module
 * boundary (same as RitualWeek).
 *
 * Key order is part of the contract, not decoration. is_locked and options sit
 * last because that is where P3 and P7 left them.
 */
final class QuestionCardPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function build(QuestionData $question, bool $liked): array
    {
        $payload = [
            'ulid' => $question->ulid,
            'body' => $question->body,
            'type' => $question->type,
            'category' => $question->category ? [
                'slug' => $question->category->slug,
                'name' => $question->category->name,
            ] : null,
            'tags' => $question->tags,
            'liked' => $liked,
        ];

        // Last, so /questions/next keeps the exact key order it has had since P3.
        $payload['is_locked'] = $question->isLocked;

        // Appended after is_locked for the same reason: every key that was there
        // in P3 stays where it was. null for an open card, {items, multiple} for
        // one answered by picking — passed through exactly as stored, so the
        // client reads options?.items and options?.multiple and nothing here has
        // to know how a picker looks.
        $payload['options'] = $question->options;

        return $payload;
    }
}
