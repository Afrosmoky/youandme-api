<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A couple's reward account — the Rewards aggregate root, extracted from
     * couples (P5 put the balance there when rewards were one mechanic; with five
     * sources they are no longer game state). Renamed on the way out:
     * couples.card_balance → couple_rewards.credits (a granted entitlement; how it
     * is spent is a later mechanic).
     *
     * unique(couple_id): one account per couple — it also makes the lazy
     * firstOrCreate in GrantCreditsAction race-safe (createOrFirst needs the
     * constraint). couple_id is a cross-module FK to Game's couples, physically OK
     * (DR-009); Rewards holds the id and never loads the Couple model.
     *
     * No ulid (the client never addresses the account). DB default 0 on credits is
     * mirrored in CoupleReward::$attributes (Eloquent create() does not load DB
     * defaults into the object — same P3/P4 pattern).
     */
    public function up(): void
    {
        Schema::create('couple_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained();
            $table->integer('credits')->default(0);
            $table->timestampTz('share_reward_claimed_at')->nullable();
            $table->timestampsTz();

            $table->unique('couple_id');
        });

        // Data migration: one account per existing couple. credits start at 0 —
        // a deliberate reset (dev/test data only, pre-launch; canon §2). The share
        // flag IS carried over: it is the "once in the account's lifetime" gate, and
        // resetting it would let a tester claim the share bonus twice.
        DB::statement(<<<'SQL'
            INSERT INTO couple_rewards (couple_id, credits, share_reward_claimed_at, created_at, updated_at)
            SELECT id, 0, share_reward_claimed_at, now(), now() FROM couples
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('couple_rewards');
    }
};
