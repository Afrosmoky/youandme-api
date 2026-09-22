<?php

namespace Youandme\Auth\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;

/**
 * Delete a user for good: every API token, any pending password reset, any web
 * session, then the row itself.
 *
 * A hard delete, not the model's soft one. users.google_id and users.apple_id are
 * plain unique indexes (only email and nickname are partial on deleted_at), so a
 * soft-deleted row would keep the provider id and the next sign-in with the same
 * Apple or Google account would hit the unique violation. And a store-mandated
 * account deletion means the data is gone, not hidden.
 *
 * Auth's tables only. Rows other modules keep against this user have to be gone
 * before this runs (several point at users.id without a cascade); the app layer
 * orchestrates that.
 */
final class DeleteUserAction
{
    use AsAction;

    public function handle(User $user): void
    {
        $user->tokens()->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->forceDelete();
    }
}
