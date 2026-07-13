<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Ritual;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ritual>
 */
class RitualFactory extends Factory
{
    /** @var class-string<Ritual> */
    protected $model = Ritual::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Tydzień '.fake()->unique()->word(),
            'body' => fake()->unique()->paragraph(),
            'locale' => 'pl',
            'ordering' => 0,
        ];
    }
}
