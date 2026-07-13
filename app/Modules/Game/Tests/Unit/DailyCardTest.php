<?php

use App\Modules\Game\Exceptions\EmptyDailyPoolException;
use App\Modules\Game\Support\DailyCard;
use App\Modules\Game\Support\DailyStreakState;
use Carbon\CarbonImmutable;

// --- card selection determinism ---

test('same couple + same date picks the same question', function (): void {
    $pool = range(10, 110);
    $date = CarbonImmutable::parse('2026-07-13');

    $a = (new DailyCard('COUPLE_ULID_AAAAAAAAAAAAAA', $date))->pick($pool);
    $b = (new DailyCard('COUPLE_ULID_AAAAAAAAAAAAAA', $date))->pick($pool);

    expect($a)->toBe($b)->and($a)->toBeIn($pool);
});

test('the same couple gets different cards across different days', function (): void {
    $pool = range(1, 100);
    $picks = [];
    for ($i = 0; $i < 30; $i++) {
        $date = CarbonImmutable::parse('2026-07-01')->addDays($i);
        $picks[] = (new DailyCard('COUPLE_ULID_AAAAAAAAAAAAAA', $date))->pick($pool);
    }

    expect(count(array_unique($picks)))->toBeGreaterThan(1);
});

test('different couples on the same day are independent', function (): void {
    $pool = range(1, 100);
    $date = CarbonImmutable::parse('2026-07-13');

    $picks = array_map(
        fn (string $ulid): int => (new DailyCard($ulid, $date))->pick($pool),
        ['C1AAAA', 'C2BBBB', 'C3CCCC', 'C4DDDD', 'C5EEEE', 'C6FFFF', 'C7GGGG', 'C8HHHH'],
    );

    expect(count(array_unique($picks)))->toBeGreaterThan(1);
});

test('an empty pool throws instead of dividing by zero', function (): void {
    (new DailyCard('X', CarbonImmutable::parse('2026-07-13')))->pick([]);
})->throws(EmptyDailyPoolException::class);

// --- streak state rule ---

test('answered today → AnsweredToday', function (): void {
    $card = new DailyCard('X', CarbonImmutable::parse('2026-07-13'));

    expect($card->streakState(CarbonImmutable::parse('2026-07-13')))
        ->toBe(DailyStreakState::AnsweredToday);
});

test('answered yesterday → AliveNotAnswered', function (): void {
    $card = new DailyCard('X', CarbonImmutable::parse('2026-07-13'));

    expect($card->streakState(CarbonImmutable::parse('2026-07-12')))
        ->toBe(DailyStreakState::AliveNotAnswered);
});

test('answered two days ago → Broken', function (): void {
    $card = new DailyCard('X', CarbonImmutable::parse('2026-07-13'));

    expect($card->streakState(CarbonImmutable::parse('2026-07-11')))
        ->toBe(DailyStreakState::Broken);
});

test('never answered (null) → Broken', function (): void {
    $card = new DailyCard('X', CarbonImmutable::parse('2026-07-13'));

    expect($card->streakState(null))->toBe(DailyStreakState::Broken);
});

// --- effective streak ---

test('effective streak is 0 when broken, the raw value otherwise', function (): void {
    $card = new DailyCard('X', CarbonImmutable::parse('2026-07-13'));

    expect($card->effectiveStreak(5, DailyStreakState::Broken))->toBe(0)
        ->and($card->effectiveStreak(5, DailyStreakState::AliveNotAnswered))->toBe(5)
        ->and($card->effectiveStreak(5, DailyStreakState::AnsweredToday))->toBe(5);
});

// --- timezone boundaries: the "day" is the couple's local calendar day ---

test('streak state uses the local calendar day in Pacific/Auckland (ahead of UTC)', function (): void {
    // UTC noon; Auckland (UTC+12 in July) has already rolled to the next day.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-13 12:00:00', 'UTC'));
    $localDate = CarbonImmutable::now('Pacific/Auckland')->startOfDay();
    expect($localDate->toDateString())->toBe('2026-07-14'); // sanity: local day is ahead of UTC

    $card = new DailyCard('X', $localDate);
    // Auckland-yesterday → alive; Auckland-today → answered. A naive UTC-date
    // comparison would misclassify both.
    expect($card->streakState(CarbonImmutable::parse('2026-07-13')))->toBe(DailyStreakState::AliveNotAnswered)
        ->and($card->streakState(CarbonImmutable::parse('2026-07-14')))->toBe(DailyStreakState::AnsweredToday);

    CarbonImmutable::setTestNow();
});

test('streak state uses the local calendar day in America/Los_Angeles (behind UTC)', function (): void {
    // 03:00 UTC; Los Angeles (UTC-7 in July) is still on the previous day.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-13 03:00:00', 'UTC'));
    $localDate = CarbonImmutable::now('America/Los_Angeles')->startOfDay();
    expect($localDate->toDateString())->toBe('2026-07-12'); // sanity: local day is behind UTC

    $card = new DailyCard('X', $localDate);
    expect($card->streakState(CarbonImmutable::parse('2026-07-11')))->toBe(DailyStreakState::AliveNotAnswered)
        ->and($card->streakState(CarbonImmutable::parse('2026-07-12')))->toBe(DailyStreakState::AnsweredToday);

    CarbonImmutable::setTestNow();
});
