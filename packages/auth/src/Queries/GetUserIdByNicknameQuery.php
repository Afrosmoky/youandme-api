<?php

namespace Youandme\Auth\Queries;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;

/**
 * Resolve a nickname to its user id, or null if no such user exists. A plain
 * nick lookup with no product concept attached — the package exposes only this,
 * so it stays reusable (canon §4); the growth mechanic that consumes it lives in
 * Game. Nickname is citext, so the match is case-insensitive at the database level.
 */
final class GetUserIdByNicknameQuery
{
    use AsAction;

    public function handle(string $nickname): ?int
    {
        return User::query()->where('nickname', $nickname)->value('id');
    }
}
