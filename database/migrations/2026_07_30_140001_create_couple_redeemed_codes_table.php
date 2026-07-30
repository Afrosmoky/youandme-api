<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which couple redeemed which code (Premium child of PromoCode). Plain pivot —
     * composite PK, no id — like couple_question_seen and its neighbours.
     *
     * The composite PK is the "once per couple" rule itself: redeeming is an
     * INSERT ... ON CONFLICT DO NOTHING whose affected-row count tells the action
     * whether this is a first redemption, so a double tap cannot burn two uses of
     * a limited code (the same shape as the unlock write in slice 1).
     *
     * couple_id is a cross-module FK to Game's couples (DR-009) held as a value —
     * Premium never loads the Couple model, which is what keeps it a leaf.
     */
    public function up(): void
    {
        Schema::create('couple_redeemed_codes', function (Blueprint $table) {
            $table->foreignId('couple_id')->constrained();
            $table->foreignId('promo_code_id')->constrained();
            $table->timestampTz('redeemed_at')->useCurrent();

            $table->primary(['couple_id', 'promo_code_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('couple_redeemed_codes');
    }
};
