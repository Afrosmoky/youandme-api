<?php

namespace App\Modules\Game\Support;

/**
 * Why a deck came back empty (S4a). The client shows a different screen for each,
 * so an empty list alone is not enough information: "pick another category" is
 * actively wrong for a couple that has played every free card, and it was what
 * they used to see.
 *
 * - OtherCategories — free (or already unlocked) cards wait in other categories;
 *                     keep them on what they already have
 * - LockedAvailable — nothing playable left anywhere, but the closed deck still
 *                     holds cards; this is the unlock funnel
 * - Complete        — the whole deck is played and there is nothing to unlock
 */
enum ExhaustionReason: string
{
    case OtherCategories = 'other_categories';
    case LockedAvailable = 'locked_available';
    case Complete = 'complete';
}
