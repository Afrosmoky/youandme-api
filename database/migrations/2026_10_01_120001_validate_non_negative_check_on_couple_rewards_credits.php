<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Step 2 of 2: check the rows that existed before the constraint. Fails if any
     * couple_rewards.credits is negative — check before deploying with
     * `SELECT couple_id, credits FROM couple_rewards WHERE credits < 0;` and fix
     * the data first (a negative balance is a bug to investigate, not to clamp).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE couple_rewards VALIDATE CONSTRAINT couple_rewards_credits_non_negative');
    }

    /**
     * Nothing to undo: an un-validated constraint is the state of step 1, and
     * Postgres has no way back from VALIDATE short of dropping it (step 1's down).
     */
    public function down(): void {}
};
