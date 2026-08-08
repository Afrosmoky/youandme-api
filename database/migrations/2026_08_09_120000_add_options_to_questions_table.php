<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multiple-choice questions (S2): 14 of the 100 deck cards are answered by
     * picking from a list instead of writing. The list is CONTENT — one more
     * thing the card says, like its body and its tags — so it sits on Catalog's
     * questions rather than in a table of its own. Options have no life outside
     * their card: nothing points at them, they are never queried on their own,
     * and their order is the order they were written in.
     *
     * Nullable with no default, deliberately: NULL means "answer in your own
     * words", a non-empty envelope means "pick". Two states, one column, no third
     * way to read it.
     *
     * The envelope is {items: string[], multiple: bool} rather than a bare array
     * plus an options_multiple column, because "how many may I pick" is only
     * meaningful when there is something to pick. Two columns would let the schema
     * express a multi-select with nothing to select; this cannot.
     *
     * No GIN index, unlike tags: nothing queries by option content, and the whole
     * deck is ~200 rows.
     *
     * Nothing about the game changes here. The pools, the anti-repeat set and the
     * played-cards report never look at this column — it is a field of content,
     * not a rule.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->jsonb('options')->nullable()->after('tags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};
