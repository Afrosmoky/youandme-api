<?php

namespace Database\Factories;

use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\GameSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameSession>
 */
class GameSessionFactory extends Factory
{
    /** @var class-string<GameSession> */
    protected $model = GameSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'couple_id' => Couple::factory(),
            'mode' => 'local',
            'category_id' => null, // mix mode by default
            'state' => [
                'remaining_ids' => [],
                'current_index' => 0,
                'draft_answer' => '',
            ],
            'started_at' => now(),
            'ended_at' => null,
        ];
    }
}
