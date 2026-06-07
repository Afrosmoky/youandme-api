<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Redundant shortcut FK to the user's active couple, avoids a JOIN on
            // couples.user_a_id for the common "give me my couple" lookup.
            $table->foreignId('active_couple_id')->nullable()->after('apple_id')->constrained('couples');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_couple_id');
        });
    }
};
