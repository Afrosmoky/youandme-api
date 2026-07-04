<?php

use App\Models\Couple;
use Youandme\Auth\Models\User;

test('couple factory creates a couple with schema defaults', function (): void {
    $couple = Couple::factory()->create();

    expect($couple->ulid)->toHaveLength(26);
    expect($couple->user_a_id)->not->toBeNull();
    expect($couple->user_b_id)->toBeNull();
    expect($couple->partner_name_local)->toBeNull();
    expect($couple->streak_current)->toBe(0);
    expect($couple->streak_longest)->toBe(0);
    expect($couple->daily_push_hour)->toBe(20);
});

test('User factory gives every user an active couple as user A', function (): void {
    $user = User::factory()->create();

    expect($user->active_couple_id)->not->toBeNull();
    expect($user->activeCouple)->not->toBeNull();
    expect($user->activeCouple->user_a_id)->toBe($user->id);
});
