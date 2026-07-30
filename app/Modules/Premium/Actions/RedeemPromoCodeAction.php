<?php

namespace App\Modules\Premium\Actions;

use App\Modules\Premium\Exceptions\InvalidPromoCodeException;
use App\Modules\Premium\Exceptions\PromoCodeAlreadyRedeemedException;
use App\Modules\Premium\Models\PromoCode;
use App\Modules\Premium\Support\PromoCodeKind;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Validate a code and record that this couple used it. Returns WHAT was bought
 * (the kind) and leaves the buying to the caller: the entitlement itself is
 * Game's to write, so Premium never learns what a full deck consists of and stays
 * a leaf (canon §1 row 6, §5).
 *
 * Takes the couple id as a plain value — same discipline as Rewards.
 *
 * Two writes, both conditional, both in one transaction with the caller's unlock
 * (the app layer opens it):
 * - the register insert is ON CONFLICT DO NOTHING, so a double tap is a 409
 *   rather than a second charge against a limited code;
 * - the counter bump is `used_count < max_uses → affected == 1`, race-safe like
 *   the ad cap, so a code with one use left cannot be redeemed twice at once.
 *
 * Lookup is case-insensitive for free: promo_codes.code is citext.
 */
final class RedeemPromoCodeAction
{
    use AsAction;

    public function handle(int $coupleId, string $code): PromoCodeKind
    {
        $promoCode = PromoCode::query()->where('code', trim($code))->first();

        if ($promoCode === null) {
            throw InvalidPromoCodeException::unknown();
        }

        if ($promoCode->expires_at !== null && $promoCode->expires_at->isPast()) {
            throw InvalidPromoCodeException::expired();
        }

        $registered = DB::table('couple_redeemed_codes')->insertOrIgnore([
            'couple_id' => $coupleId,
            'promo_code_id' => $promoCode->id,
            'redeemed_at' => now(),
        ]);

        if ($registered !== 1) {
            throw new PromoCodeAlreadyRedeemedException;
        }

        if ($promoCode->max_uses !== null) {
            $claimed = PromoCode::query()
                ->whereKey($promoCode->id)
                ->where('used_count', '<', $promoCode->max_uses)
                ->increment('used_count');

            if ($claimed !== 1) {
                // Someone took the last use first. Throwing rolls the register
                // insert back with the caller's transaction, so this couple is not
                // left marked as having redeemed a code it never got.
                throw InvalidPromoCodeException::exhausted();
            }
        } else {
            // Unlimited code: the counter is still worth keeping as a usage stat.
            PromoCode::query()->whereKey($promoCode->id)->increment('used_count');
        }

        return $promoCode->kind;
    }
}
