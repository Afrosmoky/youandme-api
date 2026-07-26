<?php

use App\Modules\Game\Actions\RecordReferralAction;
use App\Modules\Game\Models\Referral;
use App\Modules\Rewards\Models\CoupleDailyAdReward;
use App\Modules\Rewards\Models\CoupleReward;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Models\User;

/*
 | Endpoint test for POST /ad-reward. The cap and per-ad credits mirror the
 | product constants in ClaimAdRewardAction (5 ads × 1 credit); if those change,
 | these expectations change with them.
 |
 | Like the share test, this reaches into Game only to assert non-interference.
 */

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/** @return array<string, mixed> */
function watchAd(): array
{
    return test()->postJson('/api/v1/ad-reward')->assertOk()->json();
}

test('the first watched ad grants credits and opens the daily bucket', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/ad-reward')
        ->assertOk()
        ->assertExactJson([
            'granted' => true,
            'credits_awarded' => 1,
            'remaining_today' => 4,
        ]);

    expect(creditsOfCouple($coupleId))->toBe(1);

    $bucket = CoupleDailyAdReward::query()->where('couple_id', $coupleId)->firstOrFail();
    expect($bucket->count)->toBe(1)
        ->and($bucket->reward_date->toDateString())->toBe(CarbonImmutable::now()->toDateString());
});

test('ads grant up to the daily cap and remaining_today counts down', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    foreach ([4, 3, 2, 1, 0] as $expectedRemaining) {
        $body = watchAd();
        expect($body)->toBe([
            'granted' => true,
            'credits_awarded' => 1,
            'remaining_today' => $expectedRemaining,
        ]);
    }

    expect(creditsOfCouple($coupleId))->toBe(5);
});

test('the ad after the cap is a no-op: 200 granted:false, no credits, counter stands', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    foreach (range(1, 5) as $ignored) {
        watchAd();
    }

    $this->postJson('/api/v1/ad-reward')
        ->assertOk()
        ->assertExactJson([
            'granted' => false,
            'credits_awarded' => 0,
            'remaining_today' => 0,
        ]);

    expect(creditsOfCouple($coupleId))->toBe(5) // not 6
        ->and(CoupleDailyAdReward::query()->where('couple_id', $coupleId)->firstOrFail()->count)->toBe(5);
});

test('a new local day opens a fresh bucket and the couple can earn again', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-26 12:00:00', 'UTC'));

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    foreach (range(1, 5) as $ignored) {
        watchAd();
    }
    $this->postJson('/api/v1/ad-reward')->assertOk()->assertJson(['granted' => false]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-27 12:00:00', 'UTC'));

    $this->postJson('/api/v1/ad-reward')
        ->assertOk()
        ->assertExactJson([
            'granted' => true,
            'credits_awarded' => 1,
            'remaining_today' => 4,
        ]);

    expect(creditsOfCouple($coupleId))->toBe(6)                                             // 5 yesterday + 1 today
        ->and(CoupleDailyAdReward::query()->where('couple_id', $coupleId)->count())->toBe(2); // one bucket per day
});

test('the daily bucket follows the couple\'s local day, not UTC', function (): void {
    // 23:00 UTC on the 26th is already the 27th in Auckland (UTC+12).
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-26 23:00:00', 'UTC'));

    $user = createUserWithCouple(['timezone' => 'Pacific/Auckland']);
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    watchAd();

    $bucket = CoupleDailyAdReward::query()->where('couple_id', $coupleId)->firstOrFail();
    expect($bucket->reward_date->toDateString())->toBe('2026-07-27');
});

test('POST /ad-reward requires authentication', function (): void {
    $this->postJson('/api/v1/ad-reward')->assertUnauthorized();
});

test('POST /ad-reward returns 404 for a user with no couple', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/ad-reward')->assertNotFound();
});

test('the ad reward touches only Rewards state', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    Sanctum::actingAs($user);

    watchAd();

    expect(Referral::count())->toBe(0)
        ->and($couple->gameSessions()->count())->toBe(0)
        ->and(DB::table('couple_question_likes')->where('couple_id', $couple->id)->count())->toBe(0)
        ->and(CoupleReward::query()->where('couple_id', $couple->id)->firstOrFail()->share_reward_claimed_at)->toBeNull();

    $couple->refresh();
    expect($couple->streak_current)->toBe(0)
        ->and($couple->streak_longest)->toBe(0)
        ->and($couple->last_daily_answered_on)->toBeNull();
});

test('ad, share and referral bonuses sum on credits', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    RecordReferralAction::run(createUserWithCouple()->id, $user->id);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/share-reward')->assertOk(); // +5
    watchAd();                                          // +1
    watchAd();                                          // +1

    expect(creditsOfCouple($coupleId))->toBe(7);
});
