<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the reward columns from couples — their data now lives in
     * couple_rewards (created and backfilled by the previous migration, which must
     * run first: it reads share_reward_claimed_at from here).
     *
     * Separate Schema::table on purpose, so the move is create-then-drop and the
     * two steps stay readable (and reversible) on their own.
     */
    public function up(): void
    {
        Schema::table('couples', function (Blueprint $table) {
            $table->dropColumn(['card_balance', 'share_reward_claimed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('couples', function (Blueprint $table) {
            $table->integer('card_balance')->default(0)->after('daily_push_hour');
            $table->timestampTz('share_reward_claimed_at')->nullable()->after('card_balance');
        });
    }
};
