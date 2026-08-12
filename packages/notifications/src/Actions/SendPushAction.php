<?php

namespace Youandme\Notifications\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Notifications\Data\PushMessageData;
use Youandme\Notifications\Models\DeviceToken;
use Youandme\Notifications\Support\PushSenderInterface;

/**
 * Push one message to every device of a user, and report how many took it.
 *
 * A user with no registered device is silence, not an error — most users never
 * grant the permission, and the in-app refetch covers them anyway.
 *
 * Every registered device, on any platform, since T11 (#24): the APNs key is in
 * Firebase and the app carries the Push capability, so the Android-only gate the
 * callers used to pass is gone. Recipients are the rows in device_tokens, which
 * is the same as saying "devices that registered a token" — registration is what
 * decides, not the platform column.
 *
 * The optional platform filter stays as an affordance for a CALLER that has to
 * address one platform (a channel outage, copy only one client can render). No
 * caller passes it today, and no policy about platforms lives in this package.
 */
final class SendPushAction
{
    use AsAction;

    public function __construct(
        private readonly PushSenderInterface $sender,
    ) {}

    public function handle(string $userUlid, PushMessageData $message, ?string $platform = null): int
    {
        $tokens = DeviceToken::query()
            ->where('user_ulid', $userUlid)
            ->when($platform !== null, fn ($query) => $query->where('platform', $platform))
            ->pluck('token');

        $delivered = 0;

        foreach ($tokens as $token) {
            $delivered += $this->sender->send($token, $message) ? 1 : 0;
        }

        return $delivered;
    }
}
