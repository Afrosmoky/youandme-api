<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A stable identity for seeded content (P-beta, slice d).
     *
     * Until now the seed files were joined to their rows by the question's own
     * TEXT: `updateOrCreate(['body' => …])`. That works exactly as long as nobody
     * edits the text — and the first edit does not update a row, it creates a
     * second one, orphaning every couple's likes, played cards and memories that
     * pointed at the first. The same trap sat under the ritual deck.
     *
     * seed_key is the identity that survives an edit: q001..q100 for the session
     * deck, d001..d100 for the daily deck, r001..r033 for the rituals. The prefix
     * matters because `questions` holds both decks in one table and the index is
     * global. The number records the order in which a card ENTERED the deck, not
     * its place in it — order and paid/free are explicit data now, not positions.
     *
     * Nullable on purpose: it means "came from a seed file". Community questions
     * (etap III) will have none, and Postgres allows any number of NULLs under a
     * unique index, so they cost nothing here.
     *
     * The backfill lives in three separate migrations, one per pool, each with
     * its own frozen map — so a failure names the pool it happened in.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('seed_key', 16)->nullable()->unique();
        });

        Schema::table('rituals', function (Blueprint $table) {
            $table->string('seed_key', 16)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('seed_key');
        });

        Schema::table('rituals', function (Blueprint $table) {
            $table->dropColumn('seed_key');
        });
    }
};
