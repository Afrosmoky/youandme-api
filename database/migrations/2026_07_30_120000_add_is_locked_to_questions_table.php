<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The closed deck (P7): a question is either free or locked behind credits /
     * a promo code. The flag is an attribute of the CONTENT, so it belongs on
     * Catalog's questions — "this couple unlocked it" is a different fact and
     * lives in Game (couple_unlocked_questions).
     *
     * Default false: everything stays free until the seeder flips the split, and
     * every question created outside the seeder (community questions later) is
     * free unless it says otherwise. Mirrored in Question::$attributes — Eloquent
     * create() does not load DB defaults into the object (P1 pattern).
     *
     * No index: the whole deck is ~200 rows and the pool query already scans it.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });
    }
};
