<?php

namespace Youandme\Auth\Actions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Data\AuthResult;
use Youandme\Auth\Data\UserData;
use Youandme\Auth\Models\User;

class LoginUserAction
{
    use AsAction;

    /**
     * Manual credential check (not Auth::attempt): stateless API, no session.
     * See CLAUDE.md "Świadome odstępstwa z P1".
     *
     * @throws AuthenticationException on invalid credentials (rendered as 401)
     */
    public function handle(string $email, string $password): AuthResult
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException;
        }

        $token = $user->createToken('mobile')->plainTextToken;

        $user->loadMissing('activeCouple');

        return new AuthResult(UserData::fromModel($user), $token);
    }
}
