<?php

use App\Modules\Game\Actions\RecordReferralAction;
use App\Modules\Game\Models\Referral;
use App\Modules\Rewards\Actions\GrantCreditsAction;
use App\Modules\Rewards\Models\CoupleReward;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 | Endpoint test for POST /share-reward. It reaches into Game (referrals, sessions,
 | likes) only to assert non-interference — a test-level dependency; the Rewards
 | sources themselves stay a leaf (see RewardsModuleBoundaryTest).
 */

test('first POST /share-reward claims the reward: 200 {claimed:true}, flag set, +5 credits', function (): void {
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/share-reward')
        ->assertOk()
        ->assertExactJson(['claimed' => true]);

    $coupleId = activeCoupleOf($user)->id;
    $reward = CoupleReward::query()->where('couple_id', $coupleId)->firstOrFail();
    expect($reward->share_reward_claimed_at)->not->toBeNull()
        ->and($reward->credits)->toBe(5);
});

test('a second POST /share-reward is an idempotent no-op: 200, no double grant', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/share-reward')->assertOk()->assertExactJson(['claimed' => true]);
    $firstClaimedAt = CoupleReward::query()->where('couple_id', $coupleId)->firstOrFail()->share_reward_claimed_at;

    $this->postJson('/api/v1/share-reward')->assertOk()->assertExactJson(['claimed' => true]);

    $reward = CoupleReward::query()->where('couple_id', $coupleId)->firstOrFail();
    expect($reward->credits)->toBe(5)                                  // not 10
        ->and($reward->share_reward_claimed_at->equalTo($firstClaimedAt))->toBeTrue(); // stamp unchanged
});

test('POST /share-reward requires authentication', function (): void {
    $this->postJson('/api/v1/share-reward')->assertUnauthorized();
});

test('the share reward does not touch referrals, first_opened, sessions, streak, or likes', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/share-reward')->assertOk();

    // Note: first_opened_at IS set here, but by the global first-open middleware
    // (it fires on any authenticated request), not by the share action — so it is
    // not part of what "share touches". The share action's own reach is Rewards.
    expect(Referral::count())->toBe(0)
        ->and($couple->gameSessions()->count())->toBe(0)
        ->and(DB::table('couple_question_likes')->where('couple_id', $couple->id)->count())->toBe(0);

    $couple->refresh();
    expect($couple->streak_current)->toBe(0)
        ->and($couple->streak_longest)->toBe(0)
        ->and($couple->last_daily_answered_on)->toBeNull();
});

test('share and referral bonuses sum on credits', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    // Simulate an already-earned referral bonus (registrant's +5).
    GrantCreditsAction::run($couple->id, 5);
    RecordReferralAction::run(createUserWithCouple()->id, $user->id);

    Sanctum::actingAs($user);
    $this->postJson('/api/v1/share-reward')->assertOk();

    expect(creditsOfCouple($couple->id))->toBe(10); // 5 referral + 5 share
});
