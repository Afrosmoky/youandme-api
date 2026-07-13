<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A couple's weekly ritual assignment — Game state (child of Couple). Minimal
     * shape (ritual "light"): no ulid (not addressed from the client, like
     * couple_question_seen) and no completion status.
     *
     * unique(couple_id, started_on): one ritual per couple per week — this is the
     * idempotency guard for the Sunday cron. It is NOT unique(couple_id, ritual_id):
     * the 33-ritual pool cycles (a couple restarts from the longest-unused ritual
     * once exhausted), so a ritual MUST be repeatable across cycles. Within-cycle
     * anti-repeat is enforced by AssignWeeklyRitualAction, not the database.
     *
     * ritual_id crosses the module boundary (FK to Catalog's rituals) — physically
     * OK (DR-009); Game holds the id and reads content via Catalog::GetRitualByIdQuery.
     */
    public function up(): void
    {
        Schema::create('couple_weekly_rituals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained();
            $table->foreignId('ritual_id')->constrained('rituals');
            $table->date('started_on');
            $table->timestampsTz();

            $table->unique(['couple_id', 'started_on']);
        });

        // "give me the couple's current ritual" — DESC index (Blueprint cannot
        // express a descending index, so raw).
        DB::statement('CREATE INDEX couple_weekly_rituals_couple_started_index ON couple_weekly_rituals (couple_id, started_on DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('couple_weekly_rituals');
    }
};
