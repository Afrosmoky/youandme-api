<?php

namespace Youandme\Notifications\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Notifications\Models\DeviceToken;

/**
 * Remember where to push a user. Called after login and whenever the client's
 * registration token rotates (FCM reissues them freely).
 *
 * Keyed on the TOKEN, not on (user, platform): the token identifies the physical
 * install, so re-registering an existing one moves it to the current user instead
 * of leaving the previous owner subscribed to somebody else's notifications. One
 * user may hold several rows — phone plus tablet is normal.
 *
 * The user arrives as a ulid value; Notifications never resolves it to anything.
 */
final class RegisterDeviceTokenAction
{
    use AsAction;

    public function handle(string $userUlid, string $token, string $platform): DeviceToken
    {
        return DeviceToken::query()->updateOrCreate(
            ['token' => $token],
            ['user_ulid' => $userUlid, 'platform' => $platform],
        );
    }
}
