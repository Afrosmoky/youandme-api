<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\CoupleWeeklyRitual;
use App\Modules\Game\Models\GameSession;
use App\Modules\Game\Models\Referral;
use App\Modules\Memories\Models\Memory;
use App\Modules\Premium\Models\PromoCode;
use App\Modules\Progress\Models\ProgressMilestone;
use App\Modules\Rewards\Actions\GrantCreditsAction;
use App\Modules\Rewards\Actions\IssueAdRewardNonceAction;
use App\Modules\Rewards\Models\CoupleReward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Youandme\Auth\Models\User;
use Youandme\Notifications\Actions\RegisterDeviceTokenAction;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Module packages keep their tests next to their code (DR-011). Bind the same
// base TestCase + RefreshDatabase to them.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__.'/../packages/auth/src/Tests');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__.'/../app/Modules/Catalog/Tests');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__.'/../packages/notifications/src/Tests');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__.'/../app/Modules/Game/Tests');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__.'/../app/Modules/Memories/Tests');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__.'/../app/Modules/Rewards/Tests');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__.'/../app/Modules/Premium/Tests');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__.'/../app/Modules/Progress/Tests');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Create a user together with an active solo couple (Game), the way registration
 * does. The Auth UserFactory is now couple-agnostic (R1 Etap 4), so tests that
 * need the full set-up use this helper instead of relying on an auto-couple.
 *
 * @param  array<string, mixed>  $attributes
 */
function createUserWithCouple(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $couple = Couple::factory()->create(['user_a_id' => $user->id]);
    $user->active_couple_id = $couple->id;
    $user->save();

    return $user->refresh();
}

/**
 * Resolve a user's active couple (the User model no longer has the relation —
 * couple resolution lives in Game/app).
 */
function activeCoupleOf(User $user): Couple
{
    return Couple::findOrFail($user->active_couple_id);
}

/**
 * Give an account (a user from createUserWithCouple) a row in every table that
 * can hold its data: the whole footprint account deletion has to erase. Returns
 * the two outside users its referrals point at, so a test can tell them apart
 * from a control account.
 *
 * Kept in step with the schema by AccountDeletionCoverageTest, which fails when a
 * table referencing a user or a couple gets no row from here.
 *
 * @return array{referrer: User, referred: User}
 */
function seedAccountFootprint(User $user): array
{
    $couple = activeCoupleOf($user);
    $question = Question::factory()->create();

    // Memories: a live one tied to a session, and one the couple already removed.
    $session = GameSession::factory()->create(['couple_id' => $couple->id]);
    Memory::factory()->for($user)->create(['game_session_id' => $session->id, 'question_id' => $question->id]);
    Memory::factory()->for($user)->create()->delete();

    // Game.
    DB::table('couple_question_seen')->insert(['couple_id' => $couple->id, 'question_id' => $question->id, 'seen_at' => now()]);
    DB::table('couple_question_likes')->insert(['couple_id' => $couple->id, 'question_id' => $question->id]);
    DB::table('couple_unlocked_questions')->insert(['couple_id' => $couple->id, 'question_id' => $question->id, 'source' => 'credits']);
    CoupleWeeklyRitual::factory()->create(['couple_id' => $couple->id]);
    $referrer = createUserWithCouple();
    $referred = createUserWithCouple();
    Referral::create(['referrer_user_id' => $referrer->id, 'referred_user_id' => $user->id]);
    Referral::create(['referrer_user_id' => $user->id, 'referred_user_id' => $referred->id]);

    // Progress, Premium.
    DB::table('couple_milestone_unlocks')->insert(['couple_id' => $couple->id, 'milestone_id' => ProgressMilestone::factory()->create()->id]);
    DB::table('couple_redeemed_codes')->insert(['couple_id' => $couple->id, 'promo_code_id' => PromoCode::factory()->create()->id, 'redeemed_at' => now()]);

    // Rewards.
    GrantCreditsAction::run($couple->id, 3);
    DB::table('couple_daily_ad_rewards')->insert(['couple_id' => $couple->id, 'reward_date' => now()->toDateString(), 'count' => 1]);
    IssueAdRewardNonceAction::run($couple->id, $user->id);

    // Notifications.
    RegisterDeviceTokenAction::run($user->ulid, 'fcm-'.Str::random(20), 'android');

    // Auth.
    $user->createToken('mobile');
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => Str::random(40), 'created_at' => now()]);
    DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);

    return ['referrer' => $referrer, 'referred' => $referred];
}

/**
 * Read a couple's earned credits (Rewards). The reward account row is created
 * lazily on the first grant, so "no row" means "nothing granted yet" — 0.
 */
function creditsOfCouple(int $coupleId): int
{
    return (int) CoupleReward::query()
        ->where('couple_id', $coupleId)
        ->value('credits');
}

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/
