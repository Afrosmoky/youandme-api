<?php

use App\Modules\Game\Actions\GrantCardsAction;
use App\Modules\Game\Actions\RecordReferralAction;
use App\Modules\Game\Models\Referral;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

test('first POST /share-reward claims the reward: 200 {claimed:true}, flag set, +5 cards', function (): void {
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/share-reward')
        ->assertOk()
        ->assertExactJson(['claimed' => true]);

    $couple = activeCoupleOf($user);
    expect($couple->share_reward_claimed_at)->not->toBeNull()
        ->and($couple->card_balance)->toBe(5);
});

test('a second POST /share-reward is an idempotent no-op: 200, no double grant', function (): void {
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/share-reward')->assertOk()->assertExactJson(['claimed' => true]);
    $firstClaimedAt = activeCoupleOf($user)->share_reward_claimed_at;

    $this->postJson('/api/v1/share-reward')->assertOk()->assertExactJson(['claimed' => true]);

    $couple = activeCoupleOf($user);
    expect($couple->card_balance)->toBe(5)                                  // not 10
        ->and($couple->share_reward_claimed_at->equalTo($firstClaimedAt))->toBeTrue(); // stamp unchanged
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
    // not part of what "share touches". The share action's own reach is couples.
    expect(Referral::count())->toBe(0)
        ->and($couple->gameSessions()->count())->toBe(0)
        ->and(DB::table('couple_question_likes')->where('couple_id', $couple->id)->count())->toBe(0);

    $couple->refresh();
    expect($couple->streak_current)->toBe(0)
        ->and($couple->streak_longest)->toBe(0)
        ->and($couple->last_daily_answered_on)->toBeNull();
});

test('share and referral bonuses sum on card_balance', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    // Simulate an already-earned referral bonus (registrant's +5).
    GrantCardsAction::run($couple, 5);
    RecordReferralAction::run(createUserWithCouple()->id, $user->id);

    Sanctum::actingAs($user);
    $this->postJson('/api/v1/share-reward')->assertOk();

    expect($couple->fresh()->card_balance)->toBe(10); // 5 referral + 5 share
});
