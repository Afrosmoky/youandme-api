<?php

use App\Modules\Game\Support\RitualWeek;
use Carbon\CarbonImmutable;

// started_on is a Sunday (2026-07-12).

test('the start day itself is day 1', function (): void {
    expect(RitualWeek::dayOfWeek(CarbonImmutable::parse('2026-07-12'), CarbonImmutable::parse('2026-07-12')))->toBe(1);
});

test('Sunday start, Wednesday is day 4', function (): void {
    expect(RitualWeek::dayOfWeek(CarbonImmutable::parse('2026-07-12'), CarbonImmutable::parse('2026-07-15')))->toBe(4);
});

test('Saturday is day 7', function (): void {
    expect(RitualWeek::dayOfWeek(CarbonImmutable::parse('2026-07-12'), CarbonImmutable::parse('2026-07-18')))->toBe(7);
});

test('the counter clamps to 1..7', function (): void {
    // Past the week → clamps to 7.
    expect(RitualWeek::dayOfWeek(CarbonImmutable::parse('2026-07-12'), CarbonImmutable::parse('2026-07-25')))->toBe(7);
    // Before the start (should not happen) → clamps to 1.
    expect(RitualWeek::dayOfWeek(CarbonImmutable::parse('2026-07-12'), CarbonImmutable::parse('2026-07-10')))->toBe(1);
});
