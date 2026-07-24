<?php

use Youandme\Auth\Actions\MarkFirstOpenedAction;
use Youandme\Auth\Models\User;

test('MarkFirstOpenedAction sets first_opened_at and returns true on the first call', function (): void {
    $user = User::factory()->create();
    expect($user->first_opened_at)->toBeNull();

    $result = MarkFirstOpenedAction::run($user);

    expect($result)->toBeTrue()
        ->and($user->fresh()->first_opened_at)->not->toBeNull();
});

test('MarkFirstOpenedAction returns false on the second call and leaves the marker untouched', function (): void {
    $user = User::factory()->create();

    MarkFirstOpenedAction::run($user);
    $stamp = $user->fresh()->first_opened_at;

    $result = MarkFirstOpenedAction::run($user);

    expect($result)->toBeFalse()
        ->and($user->fresh()->first_opened_at->equalTo($stamp))->toBeTrue();
});
