<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The milestone dictionary (Progress aggregate root): how many cards a couple
     * must have played and what that stage is called.
     *
     * In Progress, not Catalog, deliberately: a milestone is vocabulary of the
     * PROGRESS system, not content to play. Catalog holds what a couple plays
     * (questions, categories, rituals); nothing here is ever served as a card.
     *
     * No ulid — slug is the public identifier, exactly as with categories: it is
     * unique, readable and stable, and the client filters by it rather than
     * writing it. ordering drives the map's node sequence; threshold is the rule.
     *
     * Unique on threshold as well as slug: two milestones at the same count would
     * make "the next one" ambiguous and is always a seed mistake.
     */
    public function up(): void
    {
        Schema::create('progress_milestones', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->integer('threshold')->unique();
            $table->integer('ordering')->default(0);
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress_milestones');
    }
};
