<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hearting a memory (P9). An attribute of the memory itself, not a pivot: a
     * memory belongs to exactly one couple, so "who favourited it" adds no
     * information a boolean does not already carry — unlike question likes, where
     * the same question is shared by every couple.
     *
     * The default is declared here AND in the model's $attributes (the P1 pattern):
     * Eloquent does not read DB defaults back into the object after create(), and
     * MemoryResource serializes the object.
     */
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->boolean('is_favorite')->default(false)->after('player_b_name');
        });

        // Partial index: favourites are a small subset of a couple's history, and
        // the filtered list sorts exactly like the full one (answered_at DESC, id
        // DESC as the cursor tiebreaker). Blueprint expresses neither a partial
        // index nor a descending one, hence the raw statement (same reason as
        // couple_question_seen).
        DB::statement('CREATE INDEX memories_couple_favorites_idx ON memories (couple_id, answered_at DESC, id DESC) WHERE is_favorite');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS memories_couple_favorites_idx');

        Schema::table('memories', function (Blueprint $table) {
            $table->dropColumn('is_favorite');
        });
    }
};
