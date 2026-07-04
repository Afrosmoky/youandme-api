<?php

namespace Youandme\Auth\Queries;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Data\UserData;
use Youandme\Auth\Models\User;

/**
 * Email → UserData, for the internal auth flow. Not exposed in the public HTTP
 * API (email lookups are an implementation detail).
 */
final class GetUserByEmailQuery
{
    use AsAction;

    public function handle(string $email): ?UserData
    {
        $user = User::where('email', $email)->first();

        return $user ? UserData::fromModel($user) : null;
    }
}
