<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "We did it" for the weekly ritual — the one thing P4 cut from ritual light
     * (kanon 3.5: no completion status, no weekly streak, no reward, all deferred
     * to stage II). It comes back alone and stays alone: a marker, an endpoint to
     * set it and its state in the read. No points, no badges, no streak, no effect
     * on the progress map or the milestone count. Without it the only weekly loop
     * in the app has no confirmation — and no way to measure whether anyone uses
     * it at all.
     *
     * A nullable timestamp, not a boolean: the instant is what later turns into a
     * statistic, and a boolean cannot be widened into one.
     *
     * It hangs off the assignment row, not off the couple. That is what makes the
     * history keep itself and a new week start uncompleted with nothing to reset —
     * a new week is a new row, and a new row is null.
     */
    public function up(): void
    {
        Schema::table('couple_weekly_rituals', function (Blueprint $table) {
            $table->timestampTz('completed_at')->nullable()->after('started_on');
        });
    }

    public function down(): void
    {
        Schema::table('couple_weekly_rituals', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
