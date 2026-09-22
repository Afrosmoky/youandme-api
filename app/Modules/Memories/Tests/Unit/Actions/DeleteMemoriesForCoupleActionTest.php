<?php

use App\Modules\Memories\Actions\DeleteMemoriesForCoupleAction;
use App\Modules\Memories\Models\Memory;

test('it hard-deletes the couple memories, the soft-deleted ones included', function (): void {
    $user = createUserWithCouple();
    seedAccountFootprint($user);
    $other = createUserWithCouple();
    Memory::factory()->for($other)->create();

    DeleteMemoriesForCoupleAction::run($user->active_couple_id);

    expect(Memory::withTrashed()->where('couple_id', $user->active_couple_id)->count())->toBe(0)
        ->and(Memory::withTrashed()->where('couple_id', $other->active_couple_id)->count())->toBe(1);
});
