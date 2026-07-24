<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A couple's "earned cards" counter — the single sink for bonuses (referral
     * now, share in P5 Slice 3, purchases/packs in P7). Not consumed in P5 (the
     * closed deck is P7); it grows silently. Materialized (not derived) because it
     * has many growing sources — contrast likes_count, which has one source and
     * is counted on demand.
     *
     * DB default 0 is mirrored in Couple::$attributes (Eloquent create() does not
     * load DB defaults into the object — same P3/P4 pattern).
     */
    public function up(): void
    {
        Schema::table('couples', function (Blueprint $table) {
            $table->integer('card_balance')->default(0)->after('daily_push_hour');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('couples', function (Blueprint $table) {
            $table->dropColumn('card_balance');
        });
    }
};
