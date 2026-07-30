<?php

use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Models\User;
use Youandme\Notifications\Models\DeviceToken;

/*
 | POST /device-tokens — the package's own endpoint. Note what these tests do NOT
 | need: no couple, no reward, no game state. A device belongs to a user, and that
 | is the whole world this package lives in.
 */

test('a logged-in user registers a device for push', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/device-tokens', ['token' => 'fcm-abc', 'platform' => 'android'])
        ->assertNoContent();

    expect(DeviceToken::query()->where('token', 'fcm-abc')->value('user_ulid'))->toBe($user->ulid);
});

test('the device is bound to the token holder, not to a payload field', function (): void {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/device-tokens', [
        'token' => 'fcm-abc',
        'platform' => 'android',
        // Ignored — otherwise anyone could point somebody else's notifications
        // at their own phone.
        'user_ulid' => $stranger->ulid,
    ])->assertNoContent();

    expect(DeviceToken::query()->value('user_ulid'))->toBe($user->ulid);
});

test('re-registering the same device updates the row instead of adding one', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/device-tokens', ['token' => 'fcm-old', 'platform' => 'android'])->assertNoContent();
    $this->postJson('/api/v1/device-tokens', ['token' => 'fcm-old', 'platform' => 'android'])->assertNoContent();

    expect(DeviceToken::query()->count())->toBe(1);
});

test('a rotated token is registered next to the device it replaces', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // FCM reissues tokens freely; the client re-registers and the old row ages
    // out. Pushing to a dead token is a no-op, so this is harmless.
    $this->postJson('/api/v1/device-tokens', ['token' => 'fcm-old', 'platform' => 'android'])->assertNoContent();
    $this->postJson('/api/v1/device-tokens', ['token' => 'fcm-new', 'platform' => 'android'])->assertNoContent();

    expect(DeviceToken::query()->where('user_ulid', $user->ulid)->pluck('token')->all())
        ->toBe(['fcm-old', 'fcm-new']);
});

test('an iOS token is accepted even though nothing is sent to it yet', function (): void {
    Sanctum::actingAs(User::factory()->create());

    // APNs is gated on the Apple account (T11); storing the token now avoids a
    // second client release later.
    $this->postJson('/api/v1/device-tokens', ['token' => 'apns-abc', 'platform' => 'ios'])
        ->assertNoContent();

    expect(DeviceToken::query()->value('platform'))->toBe('ios');
});

test('the platform must be one we know', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/device-tokens', ['token' => 'x', 'platform' => 'windows-phone'])
        ->assertUnprocessable();
});

test('the token is required', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/device-tokens', ['platform' => 'android'])->assertUnprocessable();
});

test('registering a device requires a token', function (): void {
    $this->postJson('/api/v1/device-tokens', ['token' => 'x', 'platform' => 'android'])
        ->assertUnauthorized();
});
