<?php

namespace Database\Seeders;

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
    }
}
