<?php

namespace Youandme\Auth\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Data\AuthResult;
use Youandme\Auth\Data\RegisterUserInput;
use Youandme\Auth\Data\UserData;
use Youandme\Auth\Data\UserRegisteredData;
use Youandme\Auth\Events\UserRegistered;
use Youandme\Auth\Models\User;

/**
 * Pure Auth registration: create the user, issue a token, emit events. The
 * couple is created by the app composition root (Game\CreateCoupleForUserAction)
 * inside the same transaction — Auth has no couple concept.
 */
final class RegisterUserAction
{
    use AsAction;

    public function handle(RegisterUserInput $input): AuthResult
    {
        $user = User::create([
            'email' => $input->email,
            'password' => $input->password,
            'nickname' => $input->nickname,
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        // Emits EmailVerificationRequested (see User::sendEmailVerificationNotification);
        // Notifications sends the mail. UserRegistered is the separate domain fact.
        $user->sendEmailVerificationNotification();

        UserRegistered::dispatch(UserRegisteredData::fromModel($user));

        return new AuthResult(UserData::fromModel($user), $token, isNewUser: true);
    }
}
