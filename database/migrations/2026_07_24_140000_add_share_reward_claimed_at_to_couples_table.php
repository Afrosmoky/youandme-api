<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "This account already claimed the share reward" — the one-time gate for the
     * share bonus. Collocated with card_balance on couples so the grant is atomic
     * with the flag check (check → add → set in one transaction, one module) —
     * the same idempotency pattern as referrals.referrer_awarded_at.
     *
     * Nullable, so no $attributes mirror needed (null is the natural default);
     * cast to datetime on the model like the other couple timestamps.
     */
    public function up(): void
    {
        Schema::table('couples', function (Blueprint $table) {
            $table->timestampTz('share_reward_claimed_at')->nullable()->after('card_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('couples', function (Blueprint $table) {
            $table->dropColumn('share_reward_claimed_at');
        });
    }
};
