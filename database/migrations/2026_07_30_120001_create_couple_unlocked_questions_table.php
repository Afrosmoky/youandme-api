<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which locked questions a couple may play (Game child of Couple). Plain
     * pivot — composite PK, no id, no timestamps beyond unlocked_at — the twin of
     * couple_question_seen and couple_question_likes.
     *
     * The composite PK is what makes unlocking idempotent AND race-safe: the
     * insert is an INSERT ... ON CONFLICT DO NOTHING and the affected-row count
     * tells the orchestrator whether to charge a credit (see
     * UnlockQuestionForCoupleAction). Two concurrent unlocks of the same card
     * therefore cost one credit, never two.
     *
     * source ∈ {credits, premium}: unlike the fungible credits balance, the
     * grantor IS known at write time, so it is recorded here (canon §2).
     * question_id is a cross-module FK to Catalog (DR-009) — Game holds the id.
     */
    public function up(): void
    {
        Schema::create('couple_unlocked_questions', function (Blueprint $table) {
            $table->foreignId('couple_id')->constrained();
            $table->foreignId('question_id')->constrained();
            $table->timestampTz('unlocked_at')->useCurrent();
            $table->string('source');

            $table->primary(['couple_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('couple_unlocked_questions');
    }
};
