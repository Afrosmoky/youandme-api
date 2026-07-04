<?php

namespace Youandme\Auth\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Events\PasswordChanged;
use Youandme\Auth\Models\User;

class ChangePasswordAction
{
    use AsAction;

    /**
     * Change the signed-in user's password, then revoke every other token so
     * other devices are logged out while the current session stays valid.
     *
     * @throws ValidationException when the current password does not match
     */
    public function handle(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Obecne hasło jest nieprawidłowe.'],
            ]);
        }

        DB::transaction(function () use ($user, $newPassword): void {
            // The 'hashed' cast on User::password hashes the plaintext on save —
            // assigning Hash::make() here would double-hash it.
            $user->password = $newPassword;
            $user->save();

            // Keep the current token so the user stays signed in; revoke the rest.
            $currentTokenId = $user->currentAccessToken()->id;
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        });

        PasswordChanged::dispatch($user->ulid);
    }
}
