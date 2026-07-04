<?php

namespace Youandme\Auth\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Youandme\Auth\Models\User;

/**
 * Pure Auth user factory — no couple. Tests that need a user with an active
 * couple use the createUserWithCouple() helper (tests/Pest.php), which wires the
 * Game couple + active_couple_id.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** @var class-string<User> */
    protected $model = User::class;

    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'nickname' => 'user_'.fake()->unique()->numberBetween(1, 999999),
            'timezone' => 'Europe/Warsaw',
            'locale' => 'pl',
        ];
    }
}
