<?php

namespace App\Modules\Game\Data;

use Spatie\LaravelData\Data;

/**
 * Couple settings edited through the user's "my settings" (PATCH /me). Only
 * partner_name_local in MVP (daily_push_hour comes later). The caller only
 * invokes the action when the field is actually present in the payload.
 */
final class UpdateCoupleSettingsInput extends Data
{
    public function __construct(
        public readonly ?string $partnerNameLocal,
    ) {}
}
