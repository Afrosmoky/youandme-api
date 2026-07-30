<?php

use App\Modules\Rewards\Actions\GrantCreditsAction;
use App\Modules\Rewards\Actions\GrantVerifiedAdRewardAction;
use App\Modules\Rewards\Actions\IssueAdRewardNonceAction;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Models\User;

/*
 | Endpoint test for GET /rewards — the first read path of the module. The ad
 | numbers mirror AdRewardPolicy (5 per day, 1 credit each).
 */

test('a fresh couple reads a zero balance without an account row', function (): void {
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/rewards')
        ->assertOk()
        ->assertExactJson([
            'credits' => 0,
            'share_reward_claimed' => false,
            'rating_reward_claimed' => false,
            'ads' => ['remaining_today' => 5, 'daily_cap' => 5],
        ]);
});

test('the balance reflects granted credits and claimed one-time rewards', function (): void {
    $user = createUserWithCouple();
    GrantCreditsAction::run(activeCoupleOf($user)->id, 7);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/share-reward')->assertOk();

    $this->getJson('/api/v1/rewards')
        ->assertOk()
        ->assertJsonPath('share_reward_claimed', true)
        ->assertJsonPath('rating_reward_claimed', false)
        // 7 granted + the share bonus.
        ->assertJsonPath('credits', fn (int $credits): bool => $credits > 7);
});

test('a verified ad lowers the remaining ad budget for today', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    // The grant now comes from the SSV webhook, so the budget is driven from the
    // verified path rather than a client call (P7 slice 2).
    GrantVerifiedAdRewardAction::run(
        IssueAdRewardNonceAction::run($coupleId, $user->id),
        CarbonImmutable::now($user->timezone)->startOfDay(),
        deckComplete: false,
    );

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/rewards')
        ->assertOk()
        ->assertJsonPath('ads.remaining_today', 4)
        ->assertJsonPath('credits', 1);
});

test('the balance requires a token', function (): void {
    $this->getJson('/api/v1/rewards')->assertUnauthorized();
});

test('a user without a couple has no reward account', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/rewards')->assertNotFound();
});
