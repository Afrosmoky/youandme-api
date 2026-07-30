<?php

use App\Modules\Rewards\Actions\GrantCreditsAction;
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

test('watching an ad lowers the remaining ad budget for today', function (): void {
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/ad-reward')->assertOk();

    $this->getJson('/api/v1/rewards')
        ->assertOk()
        ->assertJsonPath('ads.remaining_today', 4);
});

test('the balance requires a token', function (): void {
    $this->getJson('/api/v1/rewards')->assertUnauthorized();
});

test('a user without a couple has no reward account', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/rewards')->assertNotFound();
});
