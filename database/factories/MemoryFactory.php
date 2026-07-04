<?php

namespace Database\Factories;

use App\Models\Memory;
use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Models\Couple;
use Illuminate\Database\Eloquent\Factories\Factory;
use Youandme\Auth\Models\User;

/**
 * TODO Etap 5 (Memories): Memory + this factory stay in the app layer until
 * extracted. Couple is a Game model — resolved/created here (bridge) because the
 * Auth UserFactory no longer auto-creates couples.
 *
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

            if ($user === null) {
                return;
            }

            if ($user->active_couple_id === null) {
                $couple = Couple::factory()->create(['user_a_id' => $user->id]);
                $user->active_couple_id = $couple->id;
                $user->save();
            }

            $memory->couple_id ??= $user->active_couple_id;
            $memory->player_a_name ??= $user->nickname;
        });
    }
}
