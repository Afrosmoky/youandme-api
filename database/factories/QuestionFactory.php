<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /** @var class-string<Question> */
    protected $model = Question::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body' => fake()->sentence(8).'?',
            'type' => 'session',
            'locale' => 'pl',
            'is_locked' => false,
        ];
    }

    /**
     * A card from the closed part of the deck (P7) — playable only by a couple
     * that unlocked it with a credit or a promo code.
     */
    public function locked(): static
    {
        return $this->state(fn (): array => ['is_locked' => true]);
    }
}
