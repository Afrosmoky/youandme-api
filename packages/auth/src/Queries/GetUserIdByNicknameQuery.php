<?php

namespace Youandme\Auth\Queries;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;

/**
 * Resolve a nickname to its user id, or null if no such user exists. This is the
 * ONLY thing Auth knows about referrals — a plain nick lookup, with no notion of
 * "referral" in the package, so Auth stays reusable (canon §4). The referral
 * mechanic itself lives in Game. Nickname is citext, so the match is
 * case-insensitive at the database level.
 */
final class GetUserIdByNicknameQuery
{
    use AsAction;

    public function handle(string $nickname): ?int
    {
        return User::query()->where('nickname', $nickname)->value('id');
    }
}
