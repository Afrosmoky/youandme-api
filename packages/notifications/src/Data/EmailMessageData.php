<?php

namespace Youandme\Notifications\Data;

use Spatie\LaravelData\Data;

/**
 * A rendered email to send: recipient, subject, the view template and its data.
 *
 * `final` + readonly properties rather than `final readonly class`: a spatie
 * Data subclass cannot be a readonly class (its base is not readonly), so we get
 * the same immutability at the property level.
 */
final class EmailMessageData extends Data
{
    /**
     * @param  array<string, mixed>  $templateData
     */
    public function __construct(
        public readonly string $recipient,
        public readonly string $subject,
        public readonly string $template,
        public readonly array $templateData,
    ) {}
}
