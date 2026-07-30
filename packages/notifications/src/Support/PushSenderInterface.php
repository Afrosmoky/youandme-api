<?php

namespace Youandme\Notifications\Support;

use Youandme\Notifications\Data\PushMessageData;

/**
 * Delivers one message to one device.
 *
 * An Adapter over a push provider, not a domain service — the same category as
 * Auth's token verifiers, and the reason tests can bind a fake instead of talking
 * to Google.
 *
 * Implementations never throw: a push is a best-effort side channel, and the
 * caller (a webhook answering an ad network, a request answering a user) must not
 * fail because a phone could not be reached. False means "not delivered".
 */
interface PushSenderInterface
{
    public function send(string $deviceToken, PushMessageData $message): bool;
}
