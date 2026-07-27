<?php

use App\Modules\Game\Actions\RecordReferralAction;
use App\Modules\Game\Models\Referral;
use App\Modules\Rewards\Models\CoupleDailyAdReward;
use App\Modules\Rewards\Models\CoupleReward;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Models\User;

/*
 | Endpoint test for POST /rating-reward. The credit amount mirrors the product
 | constant in ClaimRatingRewardAction (5, same as share).
 |
 | Like the other reward tests, this reaches into Game only to assert
 | non-interference.
 */

test('first POST /rating-reward claims the reward: 200 {claimed:true}, flag set, +5 credits', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/rating-reward')
        ->assertOk()
        ->assertExactJson(['claimed' => true]);

    $reward = CoupleReward::query()->where('couple_id', $coupleId)->firstOrFail();
    expect($reward->rating_reward_claimed_at)->not->toBeNull()
        ->and($reward->credits)->toBe(5);
});

test('a second POST /rating-reward is an idempotent no-op: 200, no double grant', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/rating-reward')->assertOk()->assertExactJson(['claimed' => true]);
    $firstClaimedAt = CoupleReward::query()->where('couple_id', $coupleId)->firstOrFail()->rating_reward_claimed_at;

    $this->postJson('/api/v1/rating-reward')->assertOk()->assertExactJson(['claimed' => true]);

    $reward = CoupleReward::query()->where('couple_id', $coupleId)->firstOrFail();
    expect($reward->credits)->toBe(5)                                       // not 10
        ->and($reward->rating_reward_claimed_at->equalTo($firstClaimedAt))->toBeTrue(); // stamp unchanged
});

test('POST /rating-reward requires authentication', function (): void {
    $this->postJson('/api/v1/rating-reward')->assertUnauthorized();
});

test('POST /rating-reward returns 404 for a user with no couple', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/rating-reward')->assertNotFound();
});

test('the rating reward touches no other bonus or game state', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/rating-reward')->assertOk();

    $reward = CoupleReward::query()->where('couple_id', $couple->id)->firstOrFail();
    expect($reward->share_reward_claimed_at)->toBeNull()
        ->and(CoupleDailyAdReward::query()->where('couple_id', $couple->id)->count())->toBe(0)
        ->and(Referral::count())->toBe(0)
        ->and($couple->gameSessions()->count())->toBe(0)
        ->and(DB::table('couple_question_likes')->where('couple_id', $couple->id)->count())->toBe(0);

    $couple->refresh();
    expect($couple->streak_current)->toBe(0)
        ->and($couple->streak_longest)->toBe(0)
        ->and($couple->last_daily_answered_on)->toBeNull();
});

test('rating, share, ad and referral bonuses sum on credits', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    RecordReferralAction::run(createUserWithCouple()->id, $user->id);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/share-reward')->assertOk();  // +5
    $this->postJson('/api/v1/rating-reward')->assertOk(); // +5
    $this->postJson('/api/v1/ad-reward')->assertOk();     // +1

    expect(creditsOfCouple($coupleId))->toBe(11);
});
