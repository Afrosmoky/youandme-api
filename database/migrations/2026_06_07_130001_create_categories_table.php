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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            // slug is the public identifier (used in API and deep links) — no ulid.
            $table->string('slug', 40)->unique();
            $table->string('name', 80);
            $table->text('description')->nullable();
            // enum in code: 'playful' | 'reflective' — used from P11.
            $table->string('tone', 20)->nullable();
            $table->boolean('premium_only')->default(false);
            $table->smallInteger('ordering')->default(0);
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
