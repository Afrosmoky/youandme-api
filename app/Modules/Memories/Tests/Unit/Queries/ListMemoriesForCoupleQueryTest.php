<?php

use App\Modules\Memories\Data\MemoryPage;
use App\Modules\Memories\Models\Memory;
use App\Modules\Memories\Queries\ListMemoriesForCoupleQuery;

test('returns a MemoryPage with only the couple own memories', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    Memory::factory()->count(3)->create(['couple_id' => $couple->id, 'user_id' => $user->id]);

    $other = createUserWithCouple();
    Memory::factory()->create(['couple_id' => activeCoupleOf($other)->id, 'user_id' => $other->id]);

    $page = ListMemoriesForCoupleQuery::run($couple->id, null, 20);

    expect($page)->toBeInstanceOf(MemoryPage::class);
    expect($page->data)->toHaveCount(3);
    expect($page->perPage)->toBe(20);
});

test('paginates via cursor', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    Memory::factory()->count(3)->create(['couple_id' => $couple->id, 'user_id' => $user->id]);

    $page1 = ListMemoriesForCoupleQuery::run($couple->id, null, 2);
    expect($page1->data)->toHaveCount(2);
    expect($page1->nextCursor)->not->toBeNull();

    $page2 = ListMemoriesForCoupleQuery::run($couple->id, $page1->nextCursor, 2);
    expect($page2->data)->toHaveCount(1);
});
