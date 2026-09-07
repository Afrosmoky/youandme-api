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

test('a couple assigned last week gets a fresh ritual this week', function (): void {
    // Read on Wednesday 2026-07-15 with only last week's row (Sunday 2026-07-05)
    // present. Before the read was narrowed this returned the stale row forever —
    // the beta failure, clamped to "day 7 of 7".
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-15 09:00:00', 'UTC'));
    seedRituals();
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $first = Ritual::query()->orderBy('ordering')->firstOrFail();
    CoupleWeeklyRitual::create(['couple_id' => $couple->id, 'ritual_id' => $first->id, 'started_on' => '2026-07-05']);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/weekly-ritual')
        ->assertOk()
        ->assertJsonPath('started_on', '2026-07-12')
        ->assertJsonPath('day_of_week', 4);

    // A new row, and anti-repeat picked a ritual the couple has not had.
    expect($couple->weeklyRituals()->count())->toBe(2)
        ->and($couple->weeklyRituals()->where('started_on', '2026-07-12')->value('ritual_id'))->not->toBe($first->id);
    // Last week's row is untouched — the history keeps itself.
    expect($couple->weeklyRituals()->where('started_on', '2026-07-05')->value('ritual_id'))->toBe($first->id);

    CarbonImmutable::setTestNow();
});

test('a couple already assigned this week does not get a second ritual', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-15 09:00:00', 'UTC'));
    seedRituals();
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $ritual = Ritual::query()->orderBy('ordering')->firstOrFail();
    CoupleWeeklyRitual::create(['couple_id' => $couple->id, 'ritual_id' => $ritual->id, 'started_on' => '2026-07-12']);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/weekly-ritual')->assertOk()->assertJsonPath('ritual.ulid', $ritual->ulid);
    $this->getJson('/api/v1/weekly-ritual')->assertOk()->assertJsonPath('ritual.ulid', $ritual->ulid);

    expect($couple->weeklyRituals()->count())->toBe(1);

    CarbonImmutable::setTestNow();
});

test('a couple east of UTC opens its local Sunday before the cron and the cron then adds nothing', function (): void {
    // Kiritimati (UTC+14): Sunday 2026-07-12 opens at Saturday 10:00 UTC, fourteen
    // and a half hours before the 00:30 UTC run. The lazy path must write the row,
    // and the cron must then collide with the unique instead of duplicating it.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-11 12:00:00', 'UTC')); // Sunday 02:00 local
    seedRituals();
    $user = createUserWithCouple();
    $user->forceFill(['timezone' => 'Pacific/Kiritimati'])->save();
    $couple = activeCoupleOf($user);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/weekly-ritual')
        ->assertOk()
        ->assertJsonPath('started_on', '2026-07-12')
        ->assertJsonPath('day_of_week', 1);

    expect($couple->weeklyRituals()->count())->toBe(1);

    // The cron catches up at 00:30 UTC on the same calendar Sunday.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-12 00:30:00', 'UTC'));
    $this->artisan('rituals:assign-weekly')->assertSuccessful();

    expect($couple->weeklyRituals()->count())->toBe(1);

    CarbonImmutable::setTestNow();
});

test('a couple west of UTC is not shown next week\'s ritual a day early', function (): void {
    // Los Angeles (UTC-7): at Sunday 00:30 UTC it is still Saturday 17:30 there.
    // The cron writes the row for 2026-07-12; the couple must keep seeing the
    // ritual of the week they are actually in until their own Sunday arrives.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-12 00:30:00', 'UTC'));
    seedRituals();
    $user = createUserWithCouple();
    $user->forceFill(['timezone' => 'America/Los_Angeles'])->save();
    $couple = activeCoupleOf($user);
    $lastWeek = Ritual::query()->orderBy('ordering')->firstOrFail();
    CoupleWeeklyRitual::create(['couple_id' => $couple->id, 'ritual_id' => $lastWeek->id, 'started_on' => '2026-07-05']);
    Sanctum::actingAs($user);

    $this->artisan('rituals:assign-weekly')->assertSuccessful();
    expect($couple->weeklyRituals()->count())->toBe(2);

    // Saturday 17:30 local — still last week's ritual, day 7 of 7.
    $this->getJson('/api/v1/weekly-ritual')
        ->assertOk()
        ->assertJsonPath('started_on', '2026-07-05')
        ->assertJsonPath('ritual.ulid', $lastWeek->ulid)
        ->assertJsonPath('day_of_week', 7);

    // Their Sunday opens at 07:00 UTC — now the cron's row is the current one.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-12 07:30:00', 'UTC'));
    $this->getJson('/api/v1/weekly-ritual')
        ->assertOk()
        ->assertJsonPath('started_on', '2026-07-12')
        ->assertJsonPath('day_of_week', 1);

    // No third row was written along the way.
    expect($couple->weeklyRituals()->count())->toBe(2);

    CarbonImmutable::setTestNow();
});
