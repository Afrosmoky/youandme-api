<?php

use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\GameSession;

test('game session factory creates a session with a valid state shape', function (): void {
    $session = GameSession::factory()->create();

    expect($session->ulid)->toHaveLength(26);
    expect($session->mode)->toBe('local');
    expect($session->category_id)->toBeNull();
    expect($session->cards_drawn_count)->toBe(0);
    expect($session->cards_saved_count)->toBe(0);
    expect($session->ended_at)->toBeNull();

    expect($session->state)->toBeArray()
        ->toHaveKeys(['remaining_ids', 'current_index', 'draft_answer']);
    expect($session->state['remaining_ids'])->toBeArray();
    expect($session->state['current_index'])->toBeInt();
    expect($session->state['draft_answer'])->toBeString();
});

test('active scope returns only sessions without ended_at', function (): void {
    $couple = Couple::factory()->create();
    $active = GameSession::factory()->for($couple)->create();
    GameSession::factory()->for($couple)->create(['ended_at' => now()]);

    $found = GameSession::query()->active()->get();

    expect($found)->toHaveCount(1);
    expect($found->first()->id)->toBe($active->id);
});
