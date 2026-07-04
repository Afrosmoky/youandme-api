<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
function createUserWithCouple(array $attributes = []): \Youandme\Auth\Models\User
{
    $user = \Youandme\Auth\Models\User::factory()->create($attributes);
    $couple = \App\Modules\Game\Models\Couple::factory()->create(['user_a_id' => $user->id]);
    $user->active_couple_id = $couple->id;
    $user->save();

    return $user->refresh();
}

/**
 * Resolve a user's active couple (the User model no longer has the relation —
 * couple resolution lives in Game/app).
 */
function activeCoupleOf(\Youandme\Auth\Models\User $user): \App\Modules\Game\Models\Couple
{
    return \App\Modules\Game\Models\Couple::findOrFail($user->active_couple_id);
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
