<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "The current deck starts here" — the moment the couple last asked for a
     * fresh deck ("Zacznij od nowa", refactor-roadmap §12 item 19).
     *
     * couple_question_seen carries two meanings: the anti-repeat log the deal
     * reads, and the played-card register the progress map counts. A reset has to
     * renew the first without touching the second, so nothing is deleted: the
     * deal skips only cards seen at or after this instant, while the map keeps
     * counting every row.
     *
     * Nullable, so no $attributes mirror needed — null means "never reset", which
     * deals exactly as before the column existed.
     */
    public function up(): void
    {
        Schema::table('couples', function (Blueprint $table) {
            $table->timestampTz('deck_reset_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('couples', function (Blueprint $table) {
            $table->dropColumn('deck_reset_at');
        });
    }
};
