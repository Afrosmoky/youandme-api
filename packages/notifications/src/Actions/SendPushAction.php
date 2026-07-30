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
 * The optional platform filter exists so a CALLER can express "Android only"
 * without that policy leaking into the package: iOS push is gated on an APNs key
 * (Apple account, T11), so P7 sends to Android and the app layer is where that
 * decision is written down.
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
