<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /** @var class-string<Category> */
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $word = fake()->unique()->word();

        return [
            'slug' => Str::slug($word, '_'),
            'name' => ucfirst($word),
            'description' => null,
            'tone' => fake()->randomElement(['playful', 'reflective']),
            'premium_only' => false,
            'ordering' => fake()->numberBetween(0, 10),
        ];
    }
}
