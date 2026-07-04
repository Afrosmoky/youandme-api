<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

function registerWithNickname(string $nickname): TestResponse
{
    return test()->postJson('/api/v1/auth/register', [
        'email' => 'nick-'.bin2hex(random_bytes(4)).'@example.com',
        'password' => 'haslo1234',
        'nickname' => $nickname,
    ]);
}

test('register accepts a valid lowercase nickname', function (): void {
    Notification::fake();

    registerWithNickname('ola_2026')
        ->assertCreated()
        ->assertJsonPath('user.nickname', 'ola_2026');
});

test('register rejects an uppercase nickname', function (): void {
    registerWithNickname('OlaTest')
        ->assertStatus(422)
        ->assertJsonValidationErrors('nickname');
});

test('register rejects a too-short nickname', function (): void {
    registerWithNickname('ab')
        ->assertStatus(422)
        ->assertJsonValidationErrors('nickname');
});

test('register rejects a nickname with illegal characters', function (): void {
    registerWithNickname('ola.test')
        ->assertStatus(422)
        ->assertJsonValidationErrors('nickname');
});

test('register rejects a blacklisted nickname', function (): void {
    registerWithNickname('moderator')
        ->assertStatus(422)
        ->assertJsonValidationErrors('nickname');
});

test('register rejects a duplicate nickname', function (): void {
    User::factory()->create(['nickname' => 'zajety_nick']);

    registerWithNickname('zajety_nick')
        ->assertStatus(422)
        ->assertJsonValidationErrors('nickname');
});
