<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Per-couple anti-repeat log: a question, once seen, never returns. Plain
     * pivot/log table — composite PK, no id, no timestamps.
     */
    public function up(): void
    {
        Schema::create('couple_question_seen', function (Blueprint $table) {
            $table->foreignId('couple_id')->constrained();
            $table->foreignId('question_id')->constrained();
            $table->timestampTz('seen_at')->useCurrent();

            $table->primary(['couple_id', 'question_id']);
        });

        // "last N questions seen by the couple" — DESC index (Blueprint cannot
        // express a descending index, so raw).
        DB::statement('CREATE INDEX couple_question_seen_couple_seen_at_index ON couple_question_seen (couple_id, seen_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('couple_question_seen');
    }
};
