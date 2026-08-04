<?php

namespace App\Console\Commands;

use App\Modules\Game\Models\Couple;
use App\Modules\Memories\Data\MemoryData;
use App\Modules\Memories\Queries\FindLatestMemoryAnsweredWithinQuery;
use App\Support\AnniversaryKind;
use App\Support\AnniversaryWindows;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Youandme\Notifications\Actions\SendPushAction;
use Youandme\Notifications\Data\PushMessageData;

/**
 * Hourly cron: tell couples that a memory of theirs has its anniversary today.
 *
 * A command, not an event. Nobody "created an anniversary" — it arrived by the
 * calendar, and a periodic scan is what finds it. Events stay reserved for facts
 * that happened as a result of an action (AdRewardGranted).
 *
 * Hourly and timezone-aware, not one global blast: the whole point is to arrive in
 * the evening, and a fixed UTC hour would land at three in the morning for someone.
 * Each run pushes only to couples for whom it is exactly LOCAL_HOUR right now, so
 * over 24 runs every timezone is served once. Half-hour zones (India, Nepal,
 * Chatham) hear it at 18:30 local — still their evening.
 *
 * This is the app layer because the chain crosses everything: Game owns the couple,
 * Auth owns the member (and their timezone), Memories owns the history, and
 * Notifications owns the phone. None of them may know the others, so the
 * composition root walks the chain — the same reason the ad-reward push is a
 * listener here (canon §4, decision 3).
 */
class NotifyMemoryAnniversariesCommand extends Command
{
    protected $signature = 'memories:notify-anniversaries';

    protected $description = 'Push couples about memories having their monthly or yearly anniversary today.';

    /**
     * The local hour a couple hears about it. Deliberately not couples.daily_push_hour
     * (20, the daily card) and not the Sunday-evening ritual slot: three different
     * loops, three different moments, so an active couple is never pushed twice in
     * one evening.
     */
    private const LOCAL_HOUR = 18;

    /**
     * Android only, exactly as in P7: iOS push waits for an APNs key (T11). The
     * filter is a project-schedule decision, so it lives with the caller and not
     * inside the Notifications package.
     */
    private const ANDROID = 'android';

    /**
     * Long enough to outlive the local day it guards (a day can stretch to 25 hours
     * when the clocks go back), short enough to disappear on its own.
     */
    private const MARKER_HOURS = 25;

    public function handle(): int
    {
        $pushed = 0;

        // chunkById, not all(): the couples table must never be loaded whole into
        // memory by a recurring command. Members are eager-loaded because every
        // couple needs its timezone, and most will be filtered out on it.
        Couple::query()
            ->with(['userA', 'userB'])
            ->chunkById(200, function (Collection $couples) use (&$pushed): void {
                foreach ($couples as $couple) {
                    $pushed += $this->notify($couple) ? 1 : 0;
                }
            });

        $this->info("Anniversary notifications sent to {$pushed} couples.");

        return self::SUCCESS;
    }

    private function notify(Couple $couple): bool
    {
        // The couple's timezone is user_a's: couples have none of their own, and in
        // the MVP a couple is one account. A deleted member resolves to null, which
        // is simply a couple nobody can be told anything.
        $timezone = $couple->userA?->timezone;

        if ($timezone === null) {
            return false;
        }

        $now = CarbonImmutable::now($timezone);

        if ($now->hour !== self::LOCAL_HOUR) {
            return false;
        }

        $localToday = $now->startOfDay();

        // Years first: a yearly anniversary outranks a monthly one, and the newest
        // match wins a tie — which is exactly what "latest memory in any of these
        // windows" returns. One push a day, never a digest.
        $kind = AnniversaryKind::Year;
        $memory = FindLatestMemoryAnsweredWithinQuery::run($couple->id, AnniversaryWindows::years($localToday));

        if ($memory === null) {
            $kind = AnniversaryKind::Month;
            $memory = FindLatestMemoryAnsweredWithinQuery::run($couple->id, [AnniversaryWindows::month($localToday)]);
        }

        if ($memory === null) {
            return false;
        }

        // Belt and braces over the hourly filter, which already fires once a day:
        // a manual run, a retry or an overlapping schedule must not push twice.
        // Claimed before sending, so a couple gets one attempt per local day even
        // if the phones turn out to be unreachable.
        if (! Cache::add($this->markerFor($couple, $localToday), true, now()->addHours(self::MARKER_HOURS))) {
            return false;
        }

        $this->push($couple, $this->messageFor($memory, $kind, $localToday, $timezone));

        return true;
    }

    /**
     * Fan-out: one couple, up to two members, every device each of them registered.
     * Notifications never learns any of this — it is handed a user ulid and a
     * message, as values.
     */
    private function push(Couple $couple, PushMessageData $message): void
    {
        foreach ([$couple->userA, $couple->userB] as $member) {
            if ($member === null) {
                continue;
            }

            SendPushAction::run($member->ulid, $message, platform: self::ANDROID);
        }
    }

    private function messageFor(MemoryData $memory, AnniversaryKind $kind, CarbonImmutable $localToday, string $timezone): PushMessageData
    {
        // How old the memory turned today, in whole calendar years: the windows are
        // year-aligned, so the difference of the two years is exact even where the
        // month-end clamp moved the day (29 February celebrated on the 28th).
        $years = $localToday->year - CarbonImmutable::parse($memory->answeredAt)->setTimezone($timezone)->year;

        return new PushMessageData(
            title: $this->copyFor($kind, 'title', $years),
            body: $this->copyFor($kind, 'body', $years),
            data: [
                'type' => 'memory_anniversary',
                'memory_ulid' => $memory->ulid,
                'kind' => $kind->value,
                'years' => (string) $years,
            ],
        );
    }

    /**
     * trans_choice for both kinds: the yearly copy has to pluralise, the monthly
     * copy has one form and ignores the count. The locale is passed explicitly —
     * a push must never depend on whatever APP_LOCALE happens to be set to.
     *
     * (Not named line() — that is Command's own output method.)
     */
    private function copyFor(AnniversaryKind $kind, string $part, int $years): string
    {
        return trans_choice("push.memory_anniversary.{$kind->value}.{$part}", $years, [], 'pl');
    }

    private function markerFor(Couple $couple, CarbonImmutable $localToday): string
    {
        return "push:anniversary:{$couple->id}:{$localToday->toDateString()}";
    }
}
