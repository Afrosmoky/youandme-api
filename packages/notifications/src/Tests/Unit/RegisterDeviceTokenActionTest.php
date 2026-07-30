<?php

use Youandme\Notifications\Actions\RegisterDeviceTokenAction;
use Youandme\Notifications\Models\DeviceToken;

test('a device is registered for a user', function (): void {
    RegisterDeviceTokenAction::run('01JQZZZZZZZZZZZZZZZZZZZZZA', 'fcm-token-1', 'android');

    $row = DeviceToken::query()->firstOrFail();

    expect($row->user_ulid)->toBe('01JQZZZZZZZZZZZZZZZZZZZZZA')
        ->and($row->token)->toBe('fcm-token-1')
        ->and($row->platform)->toBe('android');
});

test('re-registering the same token updates it instead of duplicating', function (): void {
    RegisterDeviceTokenAction::run('01JQZZZZZZZZZZZZZZZZZZZZZA', 'fcm-token-1', 'android');
    RegisterDeviceTokenAction::run('01JQZZZZZZZZZZZZZZZZZZZZZA', 'fcm-token-1', 'android');

    expect(DeviceToken::query()->count())->toBe(1);
});

test('a device handed to another person stops pushing to the previous owner', function (): void {
    RegisterDeviceTokenAction::run('01JQZZZZZZZZZZZZZZZZZZZZZA', 'fcm-token-1', 'android');
    RegisterDeviceTokenAction::run('01JQZZZZZZZZZZZZZZZZZZZZZB', 'fcm-token-1', 'android');

    // The token is the physical install, so it moves rather than multiplying.
    expect(DeviceToken::query()->count())->toBe(1)
        ->and(DeviceToken::query()->value('user_ulid'))->toBe('01JQZZZZZZZZZZZZZZZZZZZZZB');
});

test('one user may have several devices', function (): void {
    RegisterDeviceTokenAction::run('01JQZZZZZZZZZZZZZZZZZZZZZA', 'phone', 'android');
    RegisterDeviceTokenAction::run('01JQZZZZZZZZZZZZZZZZZZZZZA', 'tablet', 'android');

    expect(DeviceToken::query()->where('user_ulid', '01JQZZZZZZZZZZZZZZZZZZZZZA')->count())->toBe(2);
});
