<?php

namespace Database\Factories;

use App\Models\Memory;
use App\Modules\Catalog\Models\Question;
use Youandme\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Memory>
 */
class MemoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'question_id' => Question::factory(),
            'game_session_id' => null,
            'origin' => 'session',
            'answer_a' => fake()->paragraph(),
            'answer_b' => null,
            'player_b_name' => null,
            'answered_at' => now(),
            // couple_id and player_a_name are derived from the creator user in
            // configure() — keeps them consistent whether the user comes from the
            // definition default or from ->for($user).
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Memory $memory): void {
            $user = $memory->user_id !== null ? User::find($memory->user_id) : null;

            if ($user !== null) {
                $memory->couple_id ??= $user->active_couple_id;
                $memory->player_a_name ??= $user->nickname;
            }
        });
    }
}
