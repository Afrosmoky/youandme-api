<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where to reach a user with a push (Notifications). The channel owns its own
     * addressing, the same way it owns mail templates.
     *
     * user_ulid is a VALUE, not a foreign key: Notifications is a reusable package
     * that must work in a project with a different user table — it may not import
     * Auth or constrain against users (canon §1 row 11). The price is no cascade
     * on user deletion; stale rows are harmless (they simply stop resolving) and
     * cheap to prune later.
     *
     * unique(token): the FCM registration token is the natural key. A device
     * handed to another person re-registers the same token under a new user_ulid,
     * and the upsert must move it rather than duplicate it — otherwise the
     * previous owner keeps receiving the new owner's pushes.
     */
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->char('user_ulid', 26);
            $table->string('token', 512);
            $table->string('platform', 16);
            $table->timestampsTz();

            $table->unique('token');
            // Every send starts with "all tokens of this user".
            $table->index('user_ulid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
