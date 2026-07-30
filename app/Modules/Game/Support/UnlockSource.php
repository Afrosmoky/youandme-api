<?php

namespace App\Modules\Game\Support;

/**
 * How a couple came by a locked card. Backed by the string persisted in
 * couple_unlocked_questions.source.
 *
 * The credits balance itself is fungible (P6/P7 Model A — no per-source wallets),
 * but the GRANTOR is known at the moment of the unlock, so it is worth recording:
 * it is the only honest source attribution in the system (canon §2).
 */
enum UnlockSource: string
{
    case Credits = 'credits';
    case Premium = 'premium';
}
