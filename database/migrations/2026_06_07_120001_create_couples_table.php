<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('couples', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_a_id')->constrained('users');
            $table->foreignId('user_b_id')->nullable()->constrained('users');
            $table->string('partner_name_local', 60)->nullable();
            $table->date('relationship_started_on')->nullable();
            $table->integer('streak_current')->default(0);
            $table->integer('streak_longest')->default(0);
            $table->date('last_daily_answered_on')->nullable();
            $table->smallInteger('daily_push_hour')->default(20);
            $table->timestampsTz();

            // Fast "find the couple where I am user B" lookup (stage III).
            $table->index('user_b_id');
        });

        // One couple per user while user_b is empty (MVP). Loosened in stage III
        // when a partner joins as user B. Partial unique via raw statement because
        // the Blueprint API cannot express a WHERE clause on an index.
        DB::statement('CREATE UNIQUE INDEX couples_user_a_id_unique_solo ON couples(user_a_id) WHERE user_b_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('couples');
    }
};
