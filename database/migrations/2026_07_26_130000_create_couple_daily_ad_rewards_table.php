<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily rewarded-ad counter (Rewards child). A BUCKET, not a log: one row per
     * (couple, local day) with an incremented count — not one row per view. A log
     * would grow linearly with views forever; the bucket is bounded by couples ×
     * retained days, and old days are pruned by rewards:prune-ad-counters.
     *
     * This is the first repeatable bonus in the project, so idempotency is a
     * counter against a daily cap, NOT a one-time ..._claimed_at flag (share,
     * rating). The cap is enforced by a conditional increment in
     * ClaimAdRewardAction (count < cap → affected == 1), never by a read-then-write.
     *
     * unique(couple_id, reward_date): one bucket per couple per day — it is also
     * what makes the lazy firstOrCreate race-safe. reward_date is the couple's
     * LOCAL day (resolved in the controller from users.timezone and passed down),
     * so "today" means their today, like the daily card.
     *
     * DB default 0 on count is mirrored in CoupleDailyAdReward::$attributes.
     */
    public function up(): void
    {
        Schema::create('couple_daily_ad_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained();
            $table->date('reward_date');
            $table->integer('count')->default(0);
            $table->timestampsTz();

            $table->unique(['couple_id', 'reward_date']);
            $table->index('reward_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('couple_daily_ad_rewards');
    }
};
