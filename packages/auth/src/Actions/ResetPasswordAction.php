<?php

namespace Youandme\Auth\Actions;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Password;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Events\PasswordChanged;
use Youandme\Auth\Models\User;

class ResetPasswordAction
{
    use AsAction;

    /**
     * Reset the password for a valid token. Returns the broker status; the
     * caller turns a non-success status into a 422.
     *
     * NOTE: does not rotate remember_token (no such column in this token-based
     * schema) and — kept 1:1 with P3 — does not revoke existing Sanctum tokens.
     */
    public function handle(string $email, string $password, string $token): string
    {
        return Password::reset(
            ['email' => $email, 'password' => $password, 'token' => $token],
            function (CanResetPassword $user, string $password): void {
                // The 'hashed' cast hashes the value on save.
                /** @var User $user */
                $user->forceFill(['password' => $password])->save();

                event(new PasswordReset($user));
                PasswordChanged::dispatch($user->ulid);
            }
        );
    }
}
