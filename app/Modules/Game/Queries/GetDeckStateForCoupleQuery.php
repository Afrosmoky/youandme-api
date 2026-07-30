<?php

namespace App\Modules\Game\Queries;

use App\Modules\Catalog\Queries\ListLockedQuestionsQuery;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Support\DeckCard;
use App\Modules\Game\Support\DeckState;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The closed deck through one couple's eyes: Catalog says which cards are locked,
 * Game's own couple_unlocked_questions says which of them this couple owns. Both
 * halves live on the Game→Catalog edge that already exists, so no new dependency
 * (canon §4).
 *
 * Counting only the intersection is deliberate: if a card is later freed
 * (is_locked flipped back), a couple that once bought it should not inflate
 * "owned out of for sale" past the total and read as complete.
 */
final class GetDeckStateForCoupleQuery
{
    use AsAction;

    public function handle(Couple $couple): DeckState
    {
        $lockedQuestions = ListLockedQuestionsQuery::run();

        /** @var list<string> $unlockedUlids */
        $unlockedUlids = $couple->unlockedQuestions()->pluck('questions.ulid')->all();
        $unlocked = array_flip($unlockedUlids);

        $cards = [];
        $unlockedCount = 0;

        foreach ($lockedQuestions as $question) {
            $isUnlocked = isset($unlocked[$question->ulid]);
            $unlockedCount += $isUnlocked ? 1 : 0;

            $cards[] = new DeckCard(
                ulid: $question->ulid,
                categorySlug: $question->category?->slug,
                categoryName: $question->category?->name,
                unlocked: $isUnlocked,
            );
        }

        return new DeckState(
            lockedTotal: count($lockedQuestions),
            unlockedCount: $unlockedCount,
            cards: $cards,
        );
    }
}
