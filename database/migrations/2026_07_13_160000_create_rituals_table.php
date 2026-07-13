<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rituals are Catalog content — a separate aggregate next to questions and
     * categories, not a question. A ritual is a title + instruction, repeated
     * daily for a week; it has no answer, category, or tags. Flat dictionary like
     * categories. "Week" is NOT a property of the content — it only appears when a
     * ritual is assigned to a couple (couple_weekly_rituals), which is why this is
     * `rituals`, not `weekly_rituals`.
     */
    public function up(): void
    {
        Schema::create('rituals', function (Blueprint $table) {
            $table->id();
            // Public identifier (exposed in the API); the client is served a
            // ritual, it does not filter by it — ulid, not slug.
            $table->char('ulid', 26)->unique();
            $table->string('title', 120);
            $table->text('body');
            $table->char('locale', 2)->default('pl');
            $table->integer('ordering')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rituals');
    }
};
