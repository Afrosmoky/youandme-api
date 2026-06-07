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
        Schema::table('questions', function (Blueprint $table) {
            // Nullable so questions without a mapped category don't break;
            // the seeder fills it from Wiktoria's deck.
            $table->foreignId('category_id')->nullable()->after('locale')->constrained('categories');
            $table->jsonb('tags')->default('[]')->after('category_id');
        });

        // GIN index for future "questions with tag X" queries (stage II).
        DB::statement('CREATE INDEX questions_tags_gin_idx ON questions USING GIN (tags)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('tags');
        });
    }
};
