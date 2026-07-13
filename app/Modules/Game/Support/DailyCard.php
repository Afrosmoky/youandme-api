<?php

namespace App\Modules\Game\Support;

use App\Modules\Game\Exceptions\EmptyDailyPoolException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The whole daily-card rule in one testable place: which question a couple gets
 * on a given local day, and what state their streak is in. Pure — it takes a
 * couple ulid and a local date and computes; it never reads the database or the
 * clock. The controller resolves the couple's local date (from users.timezone)
 * and passes it in, so this stays trivial to unit-test.
 */
final readonly class DailyCard
{
    public function __construct(
        public string $coupleUlid,
        public CarbonImmutable $localDate,
    ) {}

    /**
     * Deterministic card for (couple, day): the same couple on the same day
     * always gets the same question. As the pool grows (Wiktoria adds questions)
     * future cards shift — intended, history lives in memories.
     *
     * @param  list<int>  $poolIds  stable, id-ordered pool from Catalog
     */
    public function pick(array $poolIds): int
    {
        $count = count($poolIds);
        if ($count === 0) {
            // Guard the modulo below against division by zero (empty seed).
            throw new EmptyDailyPoolException;
        }

        $key = $this->coupleUlid.'|'.$this->localDate->toDateString();

        return $poolIds[crc32($key) % $count];
    }

    /**
     * Streak state, derived from the couple's last answered date and their local
     * date. Compared at CALENDAR-DAY granularity (toDateString) — never on Carbon
     * objects or timestamps — so a couple in Pacific/Auckland or America/Los_Angeles
     * gets the right state at the day boundary regardless of UTC offset.
     */
    public function streakState(?CarbonInterface $lastAnsweredOn): DailyStreakState
    {
        if ($lastAnsweredOn === null) {
            return DailyStreakState::Broken;
        }

        $last = $lastAnsweredOn->toDateString();

        if ($last === $this->localDate->toDateString()) {
            return DailyStreakState::AnsweredToday;
        }

        if ($last === $this->localDate->subDay()->toDateString()) {
            return DailyStreakState::AliveNotAnswered;
        }

        return DailyStreakState::Broken;
    }

    /**
     * The value to show: the stored counter, unless the streak is broken — the
     * db column can lag between a break and the next write (we expire lazily, no
     * cron), so this rule is the source of truth, not the raw column.
     */
    public function effectiveStreak(int $streakCurrent, DailyStreakState $state): int
    {
        return $state === DailyStreakState::Broken ? 0 : $streakCurrent;
    }
}
