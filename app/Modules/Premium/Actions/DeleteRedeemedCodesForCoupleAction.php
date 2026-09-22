<?php

namespace App\Modules\Premium\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Forget which codes a couple redeemed — Premium's part of deleting an account.
 *
 * This does NOT give a limited code its use back: the limit is counted by
 * promo_codes.used_count, which only ever grows (see RedeemPromoCodeAction). The
 * register row only stops one couple redeeming the same code twice.
 */
final class DeleteRedeemedCodesForCoupleAction
{
    use AsAction;

    public function handle(int $coupleId): void
    {
        DB::table('couple_redeemed_codes')->where('couple_id', $coupleId)->delete();
    }
}
