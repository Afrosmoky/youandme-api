<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Actions\UnlockQuestionForCoupleAction;
use App\Modules\Game\Support\UnlockSource;
use App\Modules\Rewards\Actions\GrantCreditsAction;
use App\Modules\Rewards\Actions\GrantVerifiedAdRewardAction;
use App\Modules\Rewards\Actions\IssueAdRewardNonceAction;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Models\User;

/*
 | Endpoint test for GET /rewards. Served by the app layer since the bulk unlock
 | (it joins Rewards, the card price and Game's deck); before that by Rewards. The
 | path, middleware and original fields must stay exactly as they were — released
 | builds read them. The ad numbers mirror AdRewardPolicy (5 per day, 1 credit
 | each); the reward values and price are pinned in RewardsEconomyContractTest.
 */

test('GET /rewards is registered exactly once, with the middleware it always had', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => $route->uri() === 'api/v1/rewards' && in_array('GET', $route->methods(), true))
        ->values();

    expect($routes)->toHaveCount(1)
        ->and($routes[0]->gatherMiddleware())->toBe(['api', 'auth:sanctum']);
});

test('a fresh couple reads a zero balance without an account row', function (): void {
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/rewards')
        ->assertOk()
        ->assertExactJson([
            // The original shape — released builds read these.
            'credits' => 0,
            'share_reward_claimed' => false,
            'rating_reward_claimed' => false,
            // Added for the client's own copy of the numbers (additive).
            'share_reward_credits' => 5,
            'rating_reward_credits' => 5,
            'unlock_cost_credits' => 1,
            'locked_remaining' => 0,
            'ads' => ['remaining_today' => 5, 'daily_cap' => 5, 'credits_per_ad' => 1],
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

test('locked_remaining counts the closed cards the couple does not own yet', function (): void {
    $locked = Question::factory()->locked()->count(3)->create();
    Question::factory()->create(); // a free card is not for sale

    $user = createUserWithCouple();
    UnlockQuestionForCoupleAction::run(activeCoupleOf($user)->id, $locked[0]->id, UnlockSource::Credits);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/rewards')
        ->assertOk()
        ->assertJsonPath('locked_remaining', 2);

    // The same number GET /deck implies, so the two screens cannot disagree.
    $deck = $this->getJson('/api/v1/deck')->assertOk()->json();
    expect($deck['locked_total'] - $deck['unlocked_count'])->toBe(2);
});

test('the balance requires a token', function (): void {
    $this->getJson('/api/v1/rewards')->assertUnauthorized();
});

test('a user without a couple has no reward account', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/rewards')->assertNotFound();
});
