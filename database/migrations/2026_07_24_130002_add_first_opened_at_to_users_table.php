<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Has this account ever opened the app" — an Auth-owned account-lifecycle
     * fact, set once on the first authenticated request. Its null → now()
     * transition is the anti-fraud gate that releases the referrer's bonus
     * (proof a live human is on the other side). Nullable; never user-settable.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('first_opened_at')->nullable()->after('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('first_opened_at');
        });
    }
};
