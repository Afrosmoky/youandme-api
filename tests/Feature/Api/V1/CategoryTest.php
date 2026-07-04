<?php

use App\Models\Category;
use Youandme\Auth\Models\User;
use Laravel\Sanctum\Sanctum;

test('authenticated user can list categories sorted by ordering', function (): void {
    Category::factory()->create(['slug' => 'randka', 'name' => 'Randka', 'ordering' => 3]);
    Category::factory()->create(['slug' => 'na_poznanie', 'name' => 'Na poznanie', 'ordering' => 1]);
    Category::factory()->create(['slug' => 'intymnosc', 'name' => 'Intymność', 'ordering' => 2]);

    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/v1/categories');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.slug', 'na_poznanie')
        ->assertJsonPath('data.1.slug', 'intymnosc')
        ->assertJsonPath('data.2.slug', 'randka');
});

test('category list response has the expected structure', function (): void {
    Category::factory()->create(['slug' => 'na_poznanie', 'name' => 'Na poznanie', 'ordering' => 1]);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                ['slug', 'name', 'description', 'tone', 'premium_only', 'ordering'],
            ],
        ])
        ->assertJsonPath('data.0.name', 'Na poznanie')
        ->assertJsonPath('data.0.ordering', 1)
        ->assertJsonPath('data.0.premium_only', false);
});

test('listing categories requires authentication', function (): void {
    $this->getJson('/api/v1/categories')->assertUnauthorized();
});
