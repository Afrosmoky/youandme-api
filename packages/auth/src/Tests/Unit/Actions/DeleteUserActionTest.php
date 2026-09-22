<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Youandme\Auth\Actions\DeleteUserAction;
use Youandme\Auth\Actions\SetActiveCoupleForUserAction;
use Youandme\Auth\Models\User;

/** Tokens, a pending reset and a web session: everything Auth keeps beside the row. */
function giveAuthFootprint(User $user): void
{
    $user->createToken('mobile');
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => Str::random(40), 'created_at' => now()]);
    DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
}

test('it hard-deletes the user with its tokens, reset tokens and sessions', function (): void {
    $user = User::factory()->create(['apple_id' => 'apple-1', 'google_id' => 'google-1']);
    giveAuthFootprint($user);
    $other = User::factory()->create();
    giveAuthFootprint($other);

    DeleteUserAction::run($user);

    expect(User::withTrashed()->whereKey($user->id)->exists())->toBeFalse()
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $other->id)->count())->toBe(1)
        ->and(DB::table('password_reset_tokens')->where('email', $other->email)->count())->toBe(1)
        ->and(DB::table('sessions')->where('user_id', $other->id)->count())->toBe(1);
});

test('the provider ids are free again once the user is gone', function (): void {
    $user = User::factory()->create(['apple_id' => 'apple-1', 'google_id' => 'google-1']);

    DeleteUserAction::run($user);

    // Full (not partial) unique indexes: a soft delete would make these throw.
    expect(User::factory()->create(['apple_id' => 'apple-1', 'google_id' => 'google-1'])->exists)->toBeTrue();
});

test('the active couple can be cleared', function (): void {
    $user = createUserWithCouple();

    SetActiveCoupleForUserAction::run($user, null);

    expect($user->fresh()->active_couple_id)->toBeNull();
});
