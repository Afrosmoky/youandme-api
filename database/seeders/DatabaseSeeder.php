<?php

namespace Database\Seeders;

use App\Modules\Catalog\Database\Seeders\CategorySeeder;
use App\Modules\Catalog\Database\Seeders\DailyQuestionSeeder;
use App\Modules\Catalog\Database\Seeders\QuestionSeeder;
use App\Modules\Catalog\Database\Seeders\RitualSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Categories first: questions FK -> categories and the seeder resolves slugs.
        $this->call(CategorySeeder::class);
        $this->call(QuestionSeeder::class);
        $this->call(DailyQuestionSeeder::class);
        $this->call(RitualSeeder::class);
    }
}
