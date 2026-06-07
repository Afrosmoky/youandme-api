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
     * Memories become couple-scoped: couple_id (owner), game_session_id (origin
     * session), origin, two answers (answer_a/answer_b for local 2-player game),
     * and snapshot player names. user_id stays as audit "who created it".
     */
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->foreignId('couple_id')->nullable()->after('id')->constrained();
            $table->foreignId('game_session_id')->nullable()->after('question_id')->constrained();
            $table->string('origin', 20)->default('session')->after('game_session_id');
            $table->text('answer_b')->nullable()->after('answer');
            $table->string('player_a_name', 60)->nullable()->after('answer_b');
            $table->string('player_b_name', 60)->nullable()->after('player_a_name');
        });

        // Rename in its own statement, after the column adds.
        Schema::table('memories', function (Blueprint $table) {
            $table->renameColumn('answer', 'answer_a');
        });

        // Backfill: every existing memory belongs to its creator's active couple.
        DB::statement('
            UPDATE memories m
            SET couple_id = u.active_couple_id,
                player_a_name = u.nickname
            FROM users u
            WHERE m.user_id = u.id AND m.couple_id IS NULL
        ');

        DB::statement('ALTER TABLE memories ALTER COLUMN couple_id SET NOT NULL');
        DB::statement('ALTER TABLE memories ALTER COLUMN player_a_name SET NOT NULL');

        // The memories list is now per-couple, not per-user.
        Schema::table('memories', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'answered_at']);
            $table->index(['couple_id', 'answered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->dropIndex(['couple_id', 'answered_at']);
            $table->index(['user_id', 'answered_at']);
        });

        Schema::table('memories', function (Blueprint $table) {
            $table->renameColumn('answer_a', 'answer');
        });

        Schema::table('memories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('couple_id');
            $table->dropConstrainedForeignId('game_session_id');
            $table->dropColumn(['origin', 'answer_b', 'player_a_name', 'player_b_name']);
        });
    }
};
