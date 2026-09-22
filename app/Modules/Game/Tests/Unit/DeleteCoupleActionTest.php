<?php

use App\Modules\Game\Actions\DeleteCoupleAction;
use App\Modules\Game\Actions\DeleteReferralsForUserAction;
use App\Modules\Game\Actions\LockCouplesOfUserAction;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\Referral;
use App\Modules\Game\Support\CoupleMembership;
use App\Modules\Memories\Actions\DeleteMemoriesForCoupleAction;
use Illuminate\Support\Facades\DB;
use Youandme\Auth\Actions\SetActiveCoupleForUserAction;

/** @return array<string, int> */
function gameRowsOfCouple(int $coupleId): array
{
    $counts = [];
    foreach (['couple_question_seen', 'couple_question_likes', 'couple_unlocked_questions', 'couple_weekly_rituals', 'game_sessions'] as $table) {
        $counts[$table] = DB::table($table)->where('couple_id', $coupleId)->count();
    }
    $counts['couples'] = DB::table('couples')->where('id', $coupleId)->count();

    return $counts;
}

test('it deletes the couple and every Game row that belongs to it', function (): void {
    $user = createUserWithCouple();
    seedAccountFootprint($user);
    $other = createUserWithCouple();
    seedAccountFootprint($other);
    $coupleId = $user->active_couple_id;

    // The caller's job, done here the way DeleteAccountAction does it.
    DeleteMemoriesForCoupleAction::run($coupleId);
    DB::table('couple_milestone_unlocks')->where('couple_id', $coupleId)->delete();
    DB::table('couple_redeemed_codes')->where('couple_id', $coupleId)->delete();
    DB::table('ad_reward_nonces')->where('couple_id', $coupleId)->delete();
    DB::table('couple_daily_ad_rewards')->where('couple_id', $coupleId)->delete();
    DB::table('couple_rewards')->where('couple_id', $coupleId)->delete();
    SetActiveCoupleForUserAction::run($user, null);

    DeleteCoupleAction::run($coupleId);

    expect(array_sum(gameRowsOfCouple($coupleId)))->toBe(0)
        ->and(gameRowsOfCouple($other->active_couple_id))->each->toBeGreaterThan(0);
});

test('it removes referrals on both sides and leaves unrelated ones', function (): void {
    $user = createUserWithCouple();
    ['referrer' => $referrer, 'referred' => $referred] = seedAccountFootprint($user);
    $unrelated = Referral::create(['referrer_user_id' => $referrer->id, 'referred_user_id' => createUserWithCouple()->id]);

    DeleteReferralsForUserAction::run($user->id);

    expect(Referral::query()->where('referrer_user_id', $user->id)->orWhere('referred_user_id', $user->id)->count())->toBe(0)
        ->and(Referral::query()->whereKey($unrelated->id)->exists())->toBeTrue()
        ->and($referred->fresh())->not->toBeNull();
});

test('it lists every couple of the user and flags a shared one', function (): void {
    $user = createUserWithCouple();
    $partner = createUserWithCouple();
    // Nothing in the app sets user_b_id yet; the remote game will.
    $shared = Couple::factory()->create(['user_a_id' => $partner->id, 'user_b_id' => $user->id]);

    $memberships = LockCouplesOfUserAction::run($user->id);

    expect($memberships)->toEqual([
        new CoupleMembership($user->active_couple_id, isShared: false),
        new CoupleMembership($shared->id, isShared: true),
    ]);
});

test('a user with no couple has no memberships', function (): void {
    $user = createUserWithCouple();
    $coupleId = $user->active_couple_id;
    SetActiveCoupleForUserAction::run($user, null);
    Couple::query()->whereKey($coupleId)->delete();

    expect(LockCouplesOfUserAction::run($user->id))->toBe([]);
});
