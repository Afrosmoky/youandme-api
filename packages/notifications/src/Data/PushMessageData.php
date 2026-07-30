<?php

namespace Youandme\Notifications\Data;

use Spatie\LaravelData\Data;

/**
 * What to show on a device. Semantic input from the caller — the package decides
 * how it reaches the phone, exactly like EmailMessageData for mail.
 *
 * `data` is a string map because that is what FCM data messages allow; callers
 * put routing hints there (what kind of event this is, what to refetch), and the
 * client uses them to open the right screen.
 */
final class PushMessageData extends Data
{
    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}
}
