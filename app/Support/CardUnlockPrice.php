<?php

namespace App\Support;

/**
 * Wiktoria's economy: what one locked card costs, in credits. A product number,
 * and the app layer is where the two modules meet — Rewards must not learn the
 * price of a card, Game must not learn that cards cost credits at all.
 *
 * Lifted out of QuestionUnlockController when more readers appeared: the bulk
 * unlock charges by it and GET /rewards reports it to the client. Pinned by
 * RewardsEconomyContractTest.
 */
final class CardUnlockPrice
{
    /** Credits charged per newly unlocked card. */
    public const CREDITS = 1;
}
