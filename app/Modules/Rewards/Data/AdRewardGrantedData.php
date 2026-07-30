<?php

namespace App\Modules\Rewards\Data;

/**
 * Payload of the AdRewardGranted event — a contract, not the models behind it, so
 * a listener never reaches into Rewards internals.
 *
 * Deviation from canon §3, deliberate: the couple and the user arrive as internal
 * ids, not ulids. Rewards is a leaf that holds couple_id / user_id as plain
 * values and cannot translate them without importing Game and Auth — the very
 * dependency the leaf position exists to prevent. Turning an id into an
 * addressable identity is cross-module work, so the app-layer listener does it.
 * The event never leaves the process, so nothing outside depends on the shape.
 *
 * (The alternative, if the canon's letter matters more later: snapshot user_ulid
 * onto ad_reward_nonces at issue time and carry it here. It costs a denormalized
 * column and buys one fewer query in the listener.)
 *
 * A plain final readonly class rather than a spatie Data: it is never serialized
 * or validated, unlike the Auth payloads that cross a package boundary.
 */
final readonly class AdRewardGrantedData
{
    public function __construct(
        public int $coupleId,
        /** Who watched the ad — the nonce owner, not "somebody in the couple". */
        public int $userId,
        public int $amount,
    ) {}
}
