<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "This account already claimed the rating reward" — the one-time gate for the
     * app-rating bonus. A column on the existing reward account (not a new table):
     * it is the same kind of fact as share_reward_claimed_at, and keeping it there
     * makes the flag check and the grant atomic within one module.
     *
     * Nullable, so no $attributes mirror needed (null is the natural default); cast
     * to datetime on the model like the share flag.
     */
    public function up(): void
    {
        Schema::table('couple_rewards', function (Blueprint $table) {
            $table->timestampTz('rating_reward_claimed_at')->nullable()->after('share_reward_claimed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('couple_rewards', function (Blueprint $table) {
            $table->dropColumn('rating_reward_claimed_at');
        });
    }
};
