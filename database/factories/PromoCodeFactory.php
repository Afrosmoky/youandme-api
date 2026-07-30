<?php

namespace Database\Factories;

use App\Modules\Premium\Models\PromoCode;
use App\Modules\Premium\Support\PromoCodeKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    /** @var class-string<PromoCode> */
    protected $model = PromoCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('JAITY-####-????')),
            'kind' => PromoCodeKind::FullDeck,
            'max_uses' => null,
            'expires_at' => null,
        ];
    }

    /** A code nobody may use any more. */
    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    /** A code with a usage budget, already spent. */
    public function exhausted(): static
    {
        return $this->state(fn (): array => ['max_uses' => 1, 'used_count' => 1]);
    }
}
