<?php

use Carbon\CarbonInterface;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Weekly ritual assignment — the project's first scheduled task. One global
// (UTC) run early Sunday; the command is idempotent. bootstrap/app.php delegates
// scheduling here (withRouting: commands), so no global-config change.
//
// 00:30 UTC (not Sunday evening) so the assignment lands before the mobile
// client's local Sunday-evening ritual push fires in any timezone — the
// earliest that push can occur is 05:00 UTC (UTC+14: Sunday 19:00 local).
Schedule::command('rituals:assign-weekly')->weeklyOn(CarbonInterface::SUNDAY, '00:30');

// Ad-reward counters are daily buckets — anything older than a week is dead
// weight (each day's cap is independent). Pruning is what keeps the "bucket, not
// log" choice bounded. 03:15 UTC daily: off-hours everywhere that matters and
// clear of the Sunday 00:30 ritual run.
Schedule::command('rewards:prune-ad-counters')->dailyAt('03:15');

// Memory anniversaries (P9). Hourly, because the push has to arrive in the
// couple's own evening: each run serves only the timezones for which it is 18:00
// local right now, so over a day every zone is covered exactly once. The command
// is idempotent per couple per local day (cache marker), so an extra run costs
// nothing but a scan.
Schedule::command('memories:notify-anniversaries')->hourly();

// SSV nonces are even shorter-lived than the buckets — issued seconds before
// their callback. Same retention (a week), five minutes later so the two prunes
// do not overlap.
Schedule::command('rewards:prune-ad-nonces')->dailyAt('03:20');
