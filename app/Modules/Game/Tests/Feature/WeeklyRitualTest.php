<?php

use App\Modules\Catalog\Models\Ritual;
use App\Modules\Game\Models\CoupleWeeklyRitual;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

function seedRituals(int $n = 5): void
{
    collect(range(0, $n - 1))->each(fn (int $i) => Ritual::factory()->create(['ordering' => $i]));
}

test('GET /weekly-ritual returns the current ritual with the right day_of_week', function (): void {
    // Wednesday 2026-07-15 (Warsaw), ritual started Sunday 2026-07-12 → day 4.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-15 09:00:00', 'UTC'));
    seedRituals();
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $ritual = Ritual::query()->orderBy('ordering')->firstOrFail();
    CoupleWeeklyRitual::create(['couple_id' => $couple->id, 'ritual_id' => $ritual->id, 'started_on' => '2026-07-12']);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/weekly-ritual')
        ->assertOk()
        ->assertJsonStructure(['ritual' => ['ulid', 'title', 'body'], 'started_on', 'day_of_week'])
        ->assertJsonPath('ritual.ulid', $ritual->ulid)
        ->assertJsonPath('started_on', '2026-07-12')
        ->assertJsonPath('day_of_week', 4);

    CarbonImmutable::setTestNow();
});

test('a fresh couple with no assignment is assigned lazily on first read', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-15 09:00:00', 'UTC')); // Wednesday
    seedRituals();
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/weekly-ritual')
        ->assertOk()
        ->assertJsonPath('started_on', '2026-07-12') // last Sunday
        ->assertJsonPath('day_of_week', 4);

    expect(activeCoupleOf($user)->weeklyRituals()->count())->toBe(1);

    CarbonImmutable::setTestNow();
});

test('GET /weekly-ritual requires authentication', function (): void {
    seedRituals();

    $this->getJson('/api/v1/weekly-ritual')->assertUnauthorized();
});

test('the cron assigns a ritual to every couple and is idempotent', function (): void {
    seedRituals();
    $u1 = createUserWithCouple();
    $u2 = createUserWithCouple();

    $this->artisan('rituals:assign-weekly')->assertSuccessful();
    expect(activeCoupleOf($u1)->weeklyRituals()->count())->toBe(1)
        ->and(activeCoupleOf($u2)->weeklyRituals()->count())->toBe(1);

    // Second run in the same week adds nothing.
    $this->artisan('rituals:assign-weekly')->assertSuccessful();
    expect(activeCoupleOf($u1)->weeklyRituals()->count())->toBe(1)
        ->and(activeCoupleOf($u2)->weeklyRituals()->count())->toBe(1);
});

test('assigning a ritual touches nothing in the session, daily-card or memory loops', function (): void {
    seedRituals();
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $couple->forceFill([
        'streak_current' => 3,
        'streak_longest' => 5,
        'last_daily_answered_on' => '2026-07-10',
    ])->save();

    $this->artisan('rituals:assign-weekly')->assertSuccessful();

    $couple->refresh();
    // Streak columns untouched.
    expect($couple->streak_current)->toBe(3)
        ->and($couple->streak_longest)->toBe(5)
        ->and($couple->last_daily_answered_on->toDateString())->toBe('2026-07-10');
    // The other three loops' tables are untouched.
    expect($couple->gameSessions()->count())->toBe(0)
        ->and($couple->seenQuestions()->count())->toBe(0)
        ->and($couple->memories()->count())->toBe(0);
    // The ritual assignment itself did land.
    expect($couple->weeklyRituals()->count())->toBe(1);
});
