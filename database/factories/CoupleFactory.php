<?php

namespace Database\Factories;

use App\Modules\Game\Models\Couple;
use Youandme\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Couple>
 */
class CoupleFactory extends Factory
{
    /** @var class-string<Couple> */
    protected $model = Couple::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_a_id' => User::factory(),
            'user_b_id' => null,
            'partner_name_local' => null,
            // streak_current, streak_longest, daily_push_hour fall back to the
            // model's $attributes defaults.
        ];
    }
}
