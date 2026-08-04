<?php

use App\Support\AnniversaryWindows;
use Carbon\CarbonImmutable;

/*
 | The calendar rule behind the anniversary push, on its own: no overflow, month-end
 | clamping, and UTC instants at the end of it.
 |
 | A pure unit test kept in the app-layer Feature suite — the app layer has no Unit
 | suite by design (the modules carry their own, CLAUDE.md).
 */

function localDay(string $date, string $timezone = 'Europe/Warsaw'): CarbonImmutable
{
    return CarbonImmutable::parse($date, $timezone)->startOfDay();
}

/**
 * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $window
 * @return array{0: string, 1: string}
 */
function windowAsUtc(array $window): array
{
    return [$window[0]->toIso8601ZuluString(), $window[1]->toIso8601ZuluString()];
}

test('the monthly window is the same day a month back, expressed in UTC', function (): void {
    // 20 May in Warsaw is CEST (+02:00), so the local day starts at 22:00 UTC the
    // day before — the offset the database comparison depends on.
    expect(windowAsUtc(AnniversaryWindows::month(localDay('2026-05-20'))))
        ->toBe(['2026-04-19T22:00:00Z', '2026-04-20T22:00:00Z']);
});

test('a month back never overflows into the next month', function (): void {
    // 31 March minus a month is 28 February, not 3 March.
    expect(windowAsUtc(AnniversaryWindows::month(localDay('2026-03-31', 'UTC'))))
        ->toBe(['2026-02-28T00:00:00Z', '2026-03-01T00:00:00Z']);
});

test('the last day of a month swallows the days the shorter month cannot name', function (): void {
    // 28 February 2027 is the last day of its month, so memories from 28, 29, 30
    // and 31 January all have their monthly anniversary today.
    expect(windowAsUtc(AnniversaryWindows::month(localDay('2027-02-28', 'UTC'))))
        ->toBe(['2027-01-28T00:00:00Z', '2027-02-01T00:00:00Z']);
});

test('an ordinary day is a single day wide', function (): void {
    expect(windowAsUtc(AnniversaryWindows::month(localDay('2026-05-15', 'UTC'))))
        ->toBe(['2026-04-15T00:00:00Z', '2026-04-16T00:00:00Z']);
});

test('yearly windows start one year back and reach MAX_YEARS', function (): void {
    $windows = AnniversaryWindows::years(localDay('2030-05-20', 'UTC'));

    expect($windows)->toHaveCount(AnniversaryWindows::MAX_YEARS);
    expect(windowAsUtc($windows[0]))->toBe(['2029-05-20T00:00:00Z', '2029-05-21T00:00:00Z']);
    expect(windowAsUtc($windows[9]))->toBe(['2020-05-20T00:00:00Z', '2020-05-21T00:00:00Z']);
});

test('a leap-day memory is celebrated on 28 February in ordinary years', function (): void {
    // 2029 has no 29 February, and the 28th is the last day of the month — so the
    // yearly window stretches over the 29th of the leap year it points at.
    $windows = AnniversaryWindows::years(localDay('2029-02-28', 'UTC'), maxYears: 1);

    expect(windowAsUtc($windows[0]))->toBe(['2028-02-28T00:00:00Z', '2028-03-01T00:00:00Z']);
});
