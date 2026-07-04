<?php

namespace Youandme\Auth\Actions;

use App\Models\Couple;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Data\AuthResult;
use Youandme\Auth\Data\RegisterUserInput;
use Youandme\Auth\Data\UserData;
use Youandme\Auth\Data\UserRegisteredData;
use Youandme\Auth\Events\UserRegistered;
use Youandme\Auth\Models\User;

class RegisterUserAction
{
    use AsAction;

    public function handle(RegisterUserInput $input): AuthResult
    {
        // Couple is created synchronously here (not via a UserRegistered
        // listener) so the response can carry it before the client makes
        // further calls. See CLAUDE.md "Wzorce z P3".
        [$user, $token] = DB::transaction(function () use ($input): array {
            $user = User::create([
                'email' => $input->email,
                'password' => $input->password,
                'nickname' => $input->nickname,
            ]);

            // TODO Etap 4 (Game): zastąpić Game\CreateCoupleForUserAction
            $couple = Couple::create(['user_a_id' => $user->id]);
            $user->active_couple_id = $couple->id;
            $user->save();
            $user->load('activeCouple');

            return [$user, $user->createToken('mobile')->plainTextToken];
        });

        // TODO Etap 3 (Notifications): zastąpić Notifications\SendEmailVerificationLinkAction + listener
        $user->sendEmailVerificationNotification();

        UserRegistered::dispatch(UserRegisteredData::fromModel($user));

        return new AuthResult(UserData::fromModel($user), $token, isNewUser: true);
    }
}
