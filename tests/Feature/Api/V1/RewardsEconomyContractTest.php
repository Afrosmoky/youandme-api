<?php

use App\Modules\Rewards\Support\AdRewardPolicy;
use App\Modules\Rewards\Support\OneTimeRewardPolicy;
use App\Support\CardUnlockPrice;
use Laravel\Sanctum\Sanctum;

/*
 | THE OTHER SIDE OF THESE NUMBERS LIVES IN ANOTHER REPO.
 |
 | youandme-mobile keeps its own copy of the reward values and the card price, for
 | releases that predate GET /rewards reporting them:
 |
 |   youandme-mobile/src/domain/rewards.ts       — the constants
 |   youandme-mobile/src/domain/rewards.test.ts  — the test pinning them
 |
 | If this test fails because you changed a value here, change it there too (and
 | the other way round) — and remember that builds already on people's phones keep
 | the old copy until they update. Changing a value is a product decision
 | (Wiktoria), not a refactor.
 */

test('the reward values and the card price are the ones the mobile app copies', function (): void {
    expect(OneTimeRewardPolicy::SHARE_REWARD_CREDITS)->toBe(5)
        ->and(OneTimeRewardPolicy::RATING_REWARD_CREDITS)->toBe(5)
        ->and(CardUnlockPrice::CREDITS)->toBe(1)
        ->and(AdRewardPolicy::CREDITS_PER_AD)->toBe(1);
});

test('GET /rewards reports exactly those values', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    $this->getJson('/api/v1/rewards')
        ->assertOk()
        ->assertJsonPath('share_reward_credits', 5)
        ->assertJsonPath('rating_reward_credits', 5)
        ->assertJsonPath('unlock_cost_credits', 1)
        ->assertJsonPath('ads.credits_per_ad', 1);
});
