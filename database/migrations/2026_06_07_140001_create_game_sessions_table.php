<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('couple_id')->constrained();
            // enum in code: 'local' | 'remote'; always 'local' in MVP.
            $table->string('mode', 20)->default('local');
            // null = mix mode (questions from all categories).
            $table->foreignId('category_id')->nullable()->constrained();
            $table->jsonb('state');
            $table->integer('cards_drawn_count')->default(0);
            $table->integer('cards_saved_count')->default(0);
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();

            // "active session for couple X" lookup.
            $table->index(['couple_id', 'ended_at']);
        });

        DB::statement('ALTER TABLE game_sessions ALTER COLUMN state SET DEFAULT \'{"remaining_ids":[],"current_index":0,"draft_answer":""}\'::jsonb');

        // At most one active (unfinished) session per couple.
        DB::statement('CREATE UNIQUE INDEX game_sessions_one_active_per_couple ON game_sessions(couple_id) WHERE ended_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
