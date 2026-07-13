<?php

use Carbon\CarbonInterface;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Weekly ritual assignment — the project's first scheduled task. One global
// (UTC) run on Sunday evening; the command is idempotent. bootstrap/app.php
// delegates scheduling here (withRouting: commands), so no global-config change.
Schedule::command('rituals:assign-weekly')->weeklyOn(CarbonInterface::SUNDAY, '18:00');
