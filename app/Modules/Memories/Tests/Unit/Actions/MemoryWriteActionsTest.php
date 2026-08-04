<?php

use App\Modules\Memories\Actions\DeleteMemoryAction;
use App\Modules\Memories\Actions\SetMemoryFavoriteAction;
use App\Modules\Memories\Actions\UpdateMemoryAnswersAction;
use App\Modules\Memories\Models\Memory;

test('SetMemoryFavoriteAction writes the state it was given', function (): void {
    $memory = Memory::factory()->for(createUserWithCouple())->create();

    expect(SetMemoryFavoriteAction::run($memory, true)->is_favorite)->toBeTrue();
    expect($memory->fresh()->is_favorite)->toBeTrue();

    expect(SetMemoryFavoriteAction::run($memory, false)->is_favorite)->toBeFalse();
    expect($memory->fresh()->is_favorite)->toBeFalse();
});

test('UpdateMemoryAnswersAction rewrites the answers and nothing else', function (): void {
    $memory = Memory::factory()->for(createUserWithCouple())->create([
        'answer_a' => 'Stara',
        'answer_b' => 'Stara partnera',
        'player_a_name' => 'ola',
        'answered_at' => '2026-05-20T10:00:00Z',
    ]);

    UpdateMemoryAnswersAction::run($memory, 'Nowa', null);

    $fresh = $memory->fresh();
    expect($fresh->answer_a)->toBe('Nowa');
    expect($fresh->answer_b)->toBeNull();
    expect($fresh->player_a_name)->toBe('ola');
    expect($fresh->answered_at->toIso8601ZuluString())->toBe('2026-05-20T10:00:00Z');
});

test('DeleteMemoryAction soft-deletes, keeping the row on disk', function (): void {
    $memory = Memory::factory()->for(createUserWithCouple())->create();

    DeleteMemoryAction::run($memory);

    expect(Memory::query()->whereKey($memory->id)->exists())->toBeFalse();
    expect(Memory::query()->withTrashed()->whereKey($memory->id)->exists())->toBeTrue();
});
