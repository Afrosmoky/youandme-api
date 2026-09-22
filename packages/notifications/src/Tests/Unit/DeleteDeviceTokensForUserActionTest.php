<?php

use Youandme\Notifications\Actions\DeleteDeviceTokensForUserAction;
use Youandme\Notifications\Actions\RegisterDeviceTokenAction;
use Youandme\Notifications\Models\DeviceToken;

test('it deletes every device token of the user and nobody else', function (): void {
    $ulid = (string) str()->ulid();
    $otherUlid = (string) str()->ulid();
    RegisterDeviceTokenAction::run($ulid, 'phone-token', 'android');
    RegisterDeviceTokenAction::run($ulid, 'tablet-token', 'android');
    RegisterDeviceTokenAction::run($otherUlid, 'other-token', 'ios');

    DeleteDeviceTokensForUserAction::run($ulid);

    expect(DeviceToken::query()->where('user_ulid', $ulid)->count())->toBe(0)
        ->and(DeviceToken::query()->where('user_ulid', $otherUlid)->count())->toBe(1);
});
