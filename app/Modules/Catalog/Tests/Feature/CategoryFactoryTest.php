<?php

use App\Modules\Catalog\Models\Category;

test('category factory creates a valid category with defaults', function (): void {
    $category = Category::factory()->create();

    expect($category->slug)->toBeString()->not->toBe('');
    expect($category->name)->toBeString()->not->toBe('');
    expect($category->premium_only)->toBeFalse();
    expect($category->ordering)->toBeInt();
    expect($category->tone)->toBeIn(['playful', 'reflective']);
});
