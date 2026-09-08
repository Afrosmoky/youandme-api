<?php

use App\Modules\Catalog\Models\Ritual;
use App\Modules\Game\Actions\SetWeeklyRitualCompletionAction;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\CoupleWeeklyRitual;
use Carbon\CarbonImmutable;

/**
 * The Action returns the assignment so the endpoint can answer from the row rather
 * than echo the request back. These pin that contract at its source: the returned
 * row's completed_at must describe the state that actually holds, including the
 * calls that write nothing.
 */
function assignmentFor(Couple $couple, string $sunday): CoupleWeeklyRitual
{
    return CoupleWeeklyRitual::create([
        'couple_id' => $couple->id,
        'ritual_id' => Ritual::factory()->create(['ordering' => 0])->id,
        'started_on' => $sunday,
    ]);
}

test('the returned assignment carries the state that was written', function (): void {
    $couple = Couple::factory()->create();
    assignmentFor($couple, '2026-07-12');
    $wednesday = CarbonImmutable::parse('2026-07-15');

    $completed = SetWeeklyRitualCompletionAction::run($couple, $wednesday, true);
    expect($completed->completed_at)->not->toBeNull();

    $undone = SetWeeklyRitualCompletionAction::run($couple, $wednesday, false);
    expect($undone->completed_at)->toBeNull();
});

test('the returned assignment carries the stored state even when nothing is written', function (): void {
    // The calls that change nothing are the ones where a response built from the
    // request would start drifting from the row: it reports what was asked for,
    // while the row reports what is.
    $couple = Couple::factory()->create();
    assignmentFor($couple, '2026-07-12');
    $wednesday = CarbonImmutable::parse('2026-07-15');

    SetWeeklyRitualCompletionAction::run($couple, $wednesday, true);
    $first = $couple->weeklyRituals()->firstOrFail()->completed_at;

    // Already completed: no write, and the row still says completed.
    $again = SetWeeklyRitualCompletionAction::run($couple, $wednesday, true);
    expect($again->completed_at)->not->toBeNull()
        ->and($again->completed_at->equalTo($first))->toBeTrue();

    // Already uncompleted: no write, and the row still says uncompleted.
    SetWeeklyRitualCompletionAction::run($couple, $wednesday, false);
    $stillUndone = SetWeeklyRitualCompletionAction::run($couple, $wednesday, false);
    expect($stillUndone->completed_at)->toBeNull();
});
