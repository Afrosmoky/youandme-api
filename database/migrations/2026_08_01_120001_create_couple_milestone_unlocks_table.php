<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which milestones a couple has reached (Progress state). Plain pivot —
     * composite PK, no id, no timestamps beyond unlocked_at — the same shape as
     * couple_question_seen, couple_question_likes and couple_unlocked_questions.
     *
     * The composite PK IS the idempotency rule: crossing a threshold that is
     * already recorded inserts nothing instead of failing, so the check may run
     * after every single card without guarding against itself. It also makes the
     * write race-safe (INSERT ... ON CONFLICT DO NOTHING).
     *
     * The register is monotonic — a milestone once reached is never revoked, which
     * is why the counter feeding the check is a lifetime stat (see
     * CountMemoriesForCoupleQuery, which counts trashed memories too).
     *
     * couple_id is a cross-module FK to Game's couples (DR-009) held as a value;
     * Progress never loads the Couple model, which is what keeps it a leaf.
     */
    public function up(): void
    {
        Schema::create('couple_milestone_unlocks', function (Blueprint $table) {
            $table->foreignId('couple_id')->constrained();
            $table->foreignId('milestone_id')->constrained('progress_milestones');
            $table->timestampTz('unlocked_at')->useCurrent();

            $table->primary(['couple_id', 'milestone_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('couple_milestone_unlocks');
    }
};
