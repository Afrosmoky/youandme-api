<?php

namespace App\Modules\Premium\Console\Commands;

use App\Modules\Premium\Models\PromoCode;
use App\Modules\Premium\Support\PromoCodeKind;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Mint a promo code. A command rather than a seeder, deliberately: codes are
 * operational data Piotr hands to real people, not fixtures — seeding fake ones
 * into every environment would be worse than useless.
 *
 * php artisan premium:make-code --max-uses=50 --expires-at=2026-12-31
 */
class MakePromoCodeCommand extends Command
{
    protected $signature = 'premium:make-code
        {code? : The code to create; a readable random one is generated when omitted}
        {--max-uses= : How many couples may redeem it (unlimited when omitted)}
        {--expires-at= : Last day it works, Y-m-d (never when omitted)}';

    protected $description = 'Create a promo code that unlocks the closed deck.';

    public function handle(): int
    {
        /** @var string|null $given */
        $given = $this->argument('code');
        $code = $given !== null ? trim($given) : 'JAITY-'.Str::upper(Str::random(8));

        if (PromoCode::query()->where('code', $code)->exists()) {
            $this->error("Code {$code} already exists.");

            return self::FAILURE;
        }

        /** @var string|null $maxUses */
        $maxUses = $this->option('max-uses');
        /** @var string|null $expiresAt */
        $expiresAt = $this->option('expires-at');

        PromoCode::query()->create([
            'code' => $code,
            'kind' => PromoCodeKind::FullDeck,
            'max_uses' => $maxUses !== null ? (int) $maxUses : null,
            // End of the given day, so a code "valid until 31.12" works all day.
            'expires_at' => $expiresAt !== null ? CarbonImmutable::parse($expiresAt)->endOfDay() : null,
        ]);

        $this->info("Created promo code {$code}.");

        return self::SUCCESS;
    }
}
