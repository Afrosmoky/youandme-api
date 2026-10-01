<?php

namespace App\Modules\Catalog\Support;

/**
 * One session question as the bulk unlock needs it: the public ulid it was asked
 * for by, the internal id the entitlement row is keyed on, and whether it is part
 * of the closed deck. Never carries the body — the caller is about to sell it.
 */
final readonly class SessionQuestionLock
{
    public function __construct(
        public string $ulid,
        public int $id,
        public bool $isLocked,
    ) {}
}
