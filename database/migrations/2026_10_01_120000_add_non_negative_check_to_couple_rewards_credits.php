<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A couple's balance can never go below zero — enforced by the database, not
     * only by the conditional decrement in SpendCreditsAction. Any future write
     * that bypasses that Action fails loudly (23514) instead of leaving a negative
     * balance nobody notices.
     *
     * Step 1 of 2: NOT VALID enforces the rule for every new write from now on
     * without scanning existing rows. The scan is the next migration (VALIDATE),
     * kept separate because Laravel runs each migration in its own transaction —
     * in one file the two steps would hold the same lock and the split would be
     * pointless. If a negative row ever existed, deploy stops at step 2 with this
     * guard already in place.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE couple_rewards ADD CONSTRAINT couple_rewards_credits_non_negative CHECK (credits >= 0) NOT VALID');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE couple_rewards DROP CONSTRAINT IF EXISTS couple_rewards_credits_non_negative');
    }
};
