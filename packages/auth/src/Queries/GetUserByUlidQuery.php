<?php

namespace Youandme\Auth\Queries;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Data\UserData;
use Youandme\Auth\Models\User;

/**
 * Public identifier (ULID) → UserData. The Public API read used by other
 * modules to resolve a user without touching Auth's Eloquent model.
 */
final class GetUserByUlidQuery
{
    use AsAction;

    public function handle(string $ulid): ?UserData
    {
        $user = User::where('ulid', $ulid)->first();

        return $user ? UserData::fromModel($user) : null;
    }
}
