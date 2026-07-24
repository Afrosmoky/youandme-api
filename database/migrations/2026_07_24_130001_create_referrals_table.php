<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referral relation — a growth mechanic, so it lives in Game (product-specific),
     * NOT in the reusable Auth package, even though a referrer nickname is a user
     * attribute (canon §4: when data ownership collides with package reusability,
     * product-specific data goes outside the package, referenced by id).
     *
     * Both FKs cross the module boundary to users (DR-009) — Game holds the ids and
     * never loads User with Eloquent; Auth exposes only a nick-resolution Query.
     *
     * referrer_awarded_at (nullable) is the payout state for the referrer's bonus
     * (paid on the referred user's first app open) — it makes the row stateful, so
     * this is an Eloquent model, not a plain pivot. unique(referred_user_id): a user
     * is referred at most once (they enter one nick at registration). referrer_user_id
     * is NOT unique (one referrer → many referred). No ulid (client never addresses it).
     */
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_user_id')->constrained('users');
            $table->foreignId('referred_user_id')->constrained('users');
            $table->timestampTz('referrer_awarded_at')->nullable();

            $table->unique('referred_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
