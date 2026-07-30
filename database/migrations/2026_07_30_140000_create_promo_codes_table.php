<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promo codes — the Premium module's own aggregate root. "Premium
     * niesklepowy" for the MVP: a code measures the intent to buy without an
     * Apple/Google developer account; real IAP is stage II (canon §1 row 5).
     *
     * code is citext, so "JAITY2026" and "jaity2026" are one code — people type
     * these by hand. Same pattern as users.email / users.nickname; the unique
     * index is created after the type change so it is built on citext and
     * therefore case-insensitive too.
     *
     * kind says what redeeming grants (only full_deck exists today; themed packs
     * are deferred with their content). max_uses is nullable = unlimited;
     * used_count is guarded by a conditional UPDATE in the redeem action, not by a
     * constraint. expires_at nullable = no expiry.
     *
     * Premium records that a code was redeemed; the entitlement it buys is written
     * by Game (canon §1 row 6), which is what keeps this module a leaf.
     */
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            // Public identifier for admin-facing use; the code string itself is
            // what a user types, but it is a secret-ish credential, not an address.
            $table->char('ulid', 26)->unique();
            $table->string('code', 64);
            $table->string('kind');
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE promo_codes ALTER COLUMN code TYPE citext');
        DB::statement('CREATE UNIQUE INDEX promo_codes_code_unique ON promo_codes(code)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
