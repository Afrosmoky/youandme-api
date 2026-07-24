<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-couple likes: a couple intentionally hearts a question. Plain
     * pivot/log table — composite PK, no id, no timestamps — twin of
     * couple_question_seen. The composite PK enforces "one like per couple per
     * question" (idempotency) at the database level.
     *
     * No "likes per question" index: the only reader (ranking) arrives in P13.
     */
    public function up(): void
    {
        Schema::create('couple_question_likes', function (Blueprint $table) {
            $table->foreignId('couple_id')->constrained();
            $table->foreignId('question_id')->constrained();
            $table->timestampTz('liked_at')->useCurrent();

            $table->primary(['couple_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('couple_question_likes');
    }
};
