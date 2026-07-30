<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-time tokens binding a rewarded-ad view to the couple that asked for it
     * (Rewards). The server hands one out at POST /ad-reward/nonce, the client
     * carries it into the ad as custom_data, and the SSV callback brings it back.
     *
     * Why it exists on top of Google's signature: custom_data is filled in by the
     * CLIENT, so the signature only proves "this ad was really served", never "to
     * whom". The nonce adds the missing half — the couple is resolved from a row
     * this server wrote, and consumed_at makes a replayed callback worthless
     * (canon §7, decision 3).
     *
     * unique(nonce): the lookup key and the thing that makes the burn a single
     * conditional UPDATE (whereNull consumed_at → affected == 1), race-safe
     * against two copies of the same callback arriving at once.
     *
     * No ulid (the nonce string IS the public address) and no timestamps() —
     * issued_at/consumed_at are the whole lifecycle. couple_id/user_id are
     * cross-module FKs held as values (DR-009); Rewards never loads those models.
     */
    public function up(): void
    {
        Schema::create('ad_reward_nonces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('couple_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('nonce', 64)->unique();
            $table->timestampTz('issued_at')->useCurrent();
            $table->timestampTz('consumed_at')->nullable();

            // Pruning reads this: nonces are ephemeral, the table must not grow
            // forever (same "bounded, not a log" thinking as the ad buckets).
            $table->index('issued_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_reward_nonces');
    }
};
