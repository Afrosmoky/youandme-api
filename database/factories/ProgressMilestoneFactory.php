<?php

namespace Database\Factories;

use App\Modules\Progress\Models\ProgressMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgressMilestone>
 */
class ProgressMilestoneFactory extends Factory
{
    /** @var class-string<ProgressMilestone> */
    protected $model = ProgressMilestone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // threshold is unique in the schema, so the sequence has to be too —
        // tests that care about a specific value pass it explicitly.
        $threshold = $this->faker->unique()->numberBetween(1, 10000);

        return [
            'slug' => 'milestone_'.$threshold,
            'name' => 'Kamień '.$threshold,
            'threshold' => $threshold,
            'ordering' => 0,
        ];
    }

    public function at(int $threshold): static
    {
        return $this->state(fn (): array => [
            'slug' => 'milestone_'.$threshold,
            'name' => 'Kamień '.$threshold,
            'threshold' => $threshold,
        ]);
    }
}
