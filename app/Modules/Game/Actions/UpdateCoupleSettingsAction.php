<?php

namespace App\Modules\Game\Actions;

use App\Modules\Game\Data\UpdateCoupleSettingsInput;
use App\Modules\Game\Models\Couple;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Update a couple's user-editable settings. Called from the app PATCH /me
 * orchestration (the user edits the couple through "my settings").
 */
final class UpdateCoupleSettingsAction
{
    use AsAction;

    public function handle(Couple $couple, UpdateCoupleSettingsInput $input): void
    {
        $couple->partner_name_local = $input->partnerNameLocal;
        $couple->save();
    }
}
