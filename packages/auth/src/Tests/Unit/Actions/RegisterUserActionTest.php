<?php

use Youandme\Auth\Actions\RegisterUserAction;
use Youandme\Auth\Data\AuthResult;
use Youandme\Auth\Data\RegisterUserInput;
use Youandme\Auth\Models\User;

test('handle creates a user and returns an AuthResult', function (): void {
    $result = RegisterUserAction::run(new RegisterUserInput(
        email: 'unit@example.com',
        password: 'tajne-haslo-123',
        nickname: 'unit_test',
    ));

    expect($result)->toBeInstanceOf(AuthResult::class);
    expect($result->user->email)->toBe('unit@example.com');
    expect($result->user->nickname)->toBe('unit_test');
    expect($result->token)->not->toBe('');
    expect($result->isNewUser)->toBeTrue();

    $this->assertDatabaseHas('users', ['email' => 'unit@example.com']);
});

test('handle is pure Auth: it does not create a couple (that is the app orchestration)', function (): void {
    $result = RegisterUserAction::run(new RegisterUserInput(
        email: 'unit2@example.com',
        password: 'tajne-haslo-123',
        nickname: 'unit_two',
    ));

    $user = User::where('ulid', $result->user->ulid)->firstOrFail();

    expect($user->active_couple_id)->toBeNull();
    $this->assertDatabaseCount('couples', 0);
});
