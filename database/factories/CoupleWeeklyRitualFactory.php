<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Ritual;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\CoupleWeeklyRitual;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoupleWeeklyRitual>
 */
class CoupleWeeklyRitualFactory extends Factory
{
    /** @var class-string<CoupleWeeklyRitual> */
    protected $model = CoupleWeeklyRitual::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'couple_id' => Couple::factory(),
            'ritual_id' => Ritual::factory(),
            'started_on' => now()->startOfWeek(CarbonInterface::SUNDAY)->toDateString(),
        ];
    }
}
