<?php

namespace App\Modules\Game\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Partial update of couple settings edited through the user's "my settings"
 * (PATCH /me). Only fields present in the payload are applied — Optional marks
 * absent fields so they are not overwritten with null.
 */
final class UpdateCoupleSettingsInput extends Data
{
    public function __construct(
        public readonly string|null|Optional $partnerNameLocal = new Optional,
    ) {}
}
