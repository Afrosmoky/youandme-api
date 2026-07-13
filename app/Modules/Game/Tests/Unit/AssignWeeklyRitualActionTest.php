<?php

use App\Modules\Catalog\Models\Ritual;
use App\Modules\Game\Actions\AssignWeeklyRitualAction;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\CoupleWeeklyRitual;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * N rituals with ordering 0..N-1 (each factory body is unique).
 *
 * @return Collection<int, Ritual>
 */
function orderedRituals(int $n): Collection
{
    return collect(range(0, $n - 1))->map(fn (int $i): Ritual => Ritual::factory()->create(['ordering' => $i]));
}

function assignOn(Couple $couple, string $sunday): void
{
    AssignWeeklyRitualAction::run($couple, CarbonImmutable::parse($sunday));
}

test('a couple with no history gets the lowest-ordering ritual', function (): void {
    $rituals = orderedRituals(5);
    $couple = Couple::factory()->create();

    assignOn($couple, '2026-07-12');

    expect($couple->weeklyRituals()->count())->toBe(1)
        ->and($couple->weeklyRituals()->first()->ritual_id)->toBe($rituals[0]->id);
});

test('a couple with history gets a ritual it has not had yet', function (): void {
    $rituals = orderedRituals(5);
    $couple = Couple::factory()->create();
    CoupleWeeklyRitual::create(['couple_id' => $couple->id, 'ritual_id' => $rituals[0]->id, 'started_on' => '2026-07-05']);

    assignOn($couple, '2026-07-12');

    $latest = $couple->weeklyRituals()->orderByDesc('started_on')->first();
    expect($latest->ritual_id)->toBe($rituals[1]->id);
});

test('running twice for the same week assigns exactly once (idempotent)', function (): void {
    orderedRituals(5);
    $couple = Couple::factory()->create();

    assignOn($couple, '2026-07-12');
    assignOn($couple, '2026-07-12');

    expect($couple->weeklyRituals()->where('started_on', '2026-07-12')->count())->toBe(1);
});

test('an exhausted pool restarts from the ritual used longest ago', function (): void {
    $rituals = orderedRituals(3);
    $couple = Couple::factory()->create();
    // Used all three, weeks apart: #0 oldest, #2 most recent.
    CoupleWeeklyRitual::create(['couple_id' => $couple->id, 'ritual_id' => $rituals[0]->id, 'started_on' => '2026-06-21']);
    CoupleWeeklyRitual::create(['couple_id' => $couple->id, 'ritual_id' => $rituals[1]->id, 'started_on' => '2026-06-28']);
    CoupleWeeklyRitual::create(['couple_id' => $couple->id, 'ritual_id' => $rituals[2]->id, 'started_on' => '2026-07-05']);

    assignOn($couple, '2026-07-12');

    $latest = $couple->weeklyRituals()->orderByDesc('started_on')->first();
    expect($latest->ritual_id)->toBe($rituals[0]->id); // longest unused
});

test('a full cycle wraps to #0 then #1 by ordering, not #0 twice', function (): void {
    $rituals = orderedRituals(3); // small pool: 3 rituals
    $couple = Couple::factory()->create();

    // Five consecutive Sundays over a 3-ritual pool.
    foreach (['2026-06-07', '2026-06-14', '2026-06-21', '2026-06-28', '2026-07-05'] as $sunday) {
        assignOn($couple, $sunday);
    }

    $sequence = $couple->weeklyRituals()->orderBy('started_on')->pluck('ritual_id')->all();

    expect($sequence)->toBe([
        $rituals[0]->id, $rituals[1]->id, $rituals[2]->id, // cycle 1: by ordering
        $rituals[0]->id, $rituals[1]->id,                  // cycle 2: wraps to #0, then #1
    ]);
});
