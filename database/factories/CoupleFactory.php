<?php

namespace Database\Factories;

use App\Models\Couple;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Couple>
 */
class CoupleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_a_id' => User::factory()->withoutCouple(),
            'user_b_id' => null,
            'partner_name_local' => null,
            // streak_current, streak_longest, daily_push_hour fall back to the
            // model's $attributes defaults.
        ];
    }
}
