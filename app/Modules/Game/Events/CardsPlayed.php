<?php

namespace App\Modules\Game\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A couple played cards — the fact the progress map is built on (P10). Emitted by
 * MarkQuestionsPlayedAction, the single write path into couple_question_seen, so
 * every loop that plays a question card (session answer, session skip, local-game
 * report) announces it exactly once, from one place.
 *
 * ShouldDispatchAfterCommit for the reason MemoryCreated carries it: every writer
 * marks cards inside a transaction, and a side effect must never be able to roll
 * the play back, nor act on cards that never landed.
 *
 * Carries the internal couple id rather than the ulid, unlike the
 * announcement-only events in this module (QuestionAnswered, CoupleCreated). This
 * one has a real consumer, and both halves of what it triggers — counting the
 * couple's played cards, recording their milestones — are keyed by id; a ulid
 * would buy nothing but a lookup straight back into Game. Same reasoning as
 * AdRewardGrantedData in Rewards, and the event never leaves the process.
 */
final readonly class CardsPlayed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $coupleId,
    ) {}
}
