<?php

namespace Youandme\Notifications\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Notifications\Models\DeviceToken;

/**
 * Stop pushing to a user who is being deleted. device_tokens is keyed by the
 * user's ulid with no foreign key (Notifications knows no users table), so
 * nothing would ever clean these rows up on its own.
 */
final class DeleteDeviceTokensForUserAction
{
    use AsAction;

    public function handle(string $userUlid): void
    {
        DeviceToken::query()->where('user_ulid', $userUlid)->delete();
    }
}
