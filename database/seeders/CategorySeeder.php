<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'na_poznanie', 'name' => 'Na poznanie', 'ordering' => 1, 'tone' => 'reflective'],
            ['slug' => 'intymnosc', 'name' => 'Intymność', 'ordering' => 2, 'tone' => 'reflective'],
            ['slug' => 'randka', 'name' => 'Randka', 'ordering' => 3, 'tone' => 'playful'],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(['slug' => $cat['slug']], $cat);
        }
    }
}
