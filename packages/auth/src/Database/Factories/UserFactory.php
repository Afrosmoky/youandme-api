<?php

namespace Youandme\Auth\Database\Factories;

use App\Models\Couple;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Youandme\Auth\Models\User;

/**
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

    /**
     * Mirror registration: every created user gets an active couple atomically,
     * so existing tests keep working and User::factory()->create() returns a user
     * with activeCouple already set.
     *
     * TODO Etap 4 (Game): couple creation belongs to Game::CreateCoupleForUserAction;
     * kept inline here during R1 migration (Game not yet extracted).
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->active_couple_id !== null) {
                return;
            }

            DB::transaction(function () use ($user): void {
                $couple = Couple::create(['user_a_id' => $user->id]);
                $user->active_couple_id = $couple->id;
                $user->save();
            });
        });
    }

    /**
     * Skip the auto-couple afterCreating hook. Used by CoupleFactory, which
     * creates the couple itself — otherwise the user would get a second solo
     * couple and hit the partial-unique index (one solo couple per user).
     */
    public function withoutCouple(): static
    {
        $clone = clone $this;
        // Auto-couple is the only afterCreating callback; clearing it is enough.
        $clone->afterCreating = collect();

        return $clone;
    }
}
