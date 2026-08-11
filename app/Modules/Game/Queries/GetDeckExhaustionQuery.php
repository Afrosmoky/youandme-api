<?php

namespace App\Modules\Game\Queries;

use App\Modules\Catalog\Queries\CountLockedUnavailableQuestionsQuery;
use App\Modules\Catalog\Queries\CountPlayableQuestionsOutsideCategoryQuery;
use App\Modules\Game\Support\DeckExhaustion;
use App\Modules\Game\Support\ExhaustionReason;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reads why a deck request came back empty (S4a). Called ONLY from that branch —
 * a deck that dealt cards costs exactly what it cost before, and this costs at
 * most two aggregates on top (one in mix mode).
 *
 * Takes the two id lists the deck endpoint already holds rather than a Couple, so
 * the counts are answered from the same sets the pool was built from — asking
 * Game's tables a second time could not disagree, but it would be a second read
 * for nothing.
 */
final class GetDeckExhaustionQuery
{
    use AsAction;

    /**
     * @param  list<int>  $seenQuestionIds
     * @param  list<int>  $unlockedQuestionIds
     * @param  string|null  $categorySlug  null = mix
     */
    public function handle(
        array $seenQuestionIds,
        array $unlockedQuestionIds,
        ?string $categorySlug,
    ): DeckExhaustion {
        // Mix already spans every category, so there is nothing "elsewhere" by
        // definition — and not asking is what makes that structural rather than a
        // count that happens to come back zero.
        $otherPlayable = $categorySlug === null
            ? 0
            : CountPlayableQuestionsOutsideCategoryQuery::run($seenQuestionIds, $unlockedQuestionIds, $categorySlug);

        $lockedRemaining = CountLockedUnavailableQuestionsQuery::run($seenQuestionIds, $unlockedQuestionIds);

        $reason = match (true) {
            // Keep them on cards they already have before selling anything.
            $otherPlayable > 0 => ExhaustionReason::OtherCategories,
            $lockedRemaining > 0 => ExhaustionReason::LockedAvailable,
            // Presupposes a seeded deck: with an empty questions table this is
            // also what comes out, which is a dev/test artefact, not a user path.
            default => ExhaustionReason::Complete,
        };

        return new DeckExhaustion($reason, $lockedRemaining);
    }
}
