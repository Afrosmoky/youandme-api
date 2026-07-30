<?php

use Mockery\MockInterface;
use Youandme\Notifications\Actions\RegisterDeviceTokenAction;
use Youandme\Notifications\Actions\SendPushAction;
use Youandme\Notifications\Data\PushMessageData;
use Youandme\Notifications\Support\PushSenderInterface;

const PUSH_USER = '01JQZZZZZZZZZZZZZZZZZZZZZA';

function pushMessage(): PushMessageData
{
    return new PushMessageData(title: 'Kredyt', body: 'Nagroda na koncie.', data: ['type' => 'test']);
}

test('the message goes to every device of the user', function (): void {
    RegisterDeviceTokenAction::run(PUSH_USER, 'phone', 'android');
    RegisterDeviceTokenAction::run(PUSH_USER, 'tablet', 'android');

    $this->mock(PushSenderInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('send')->once()->with('phone', Mockery::type(PushMessageData::class))->andReturnTrue();
        $mock->shouldReceive('send')->once()->with('tablet', Mockery::type(PushMessageData::class))->andReturnTrue();
    });

    expect(SendPushAction::run(PUSH_USER, pushMessage()))->toBe(2);
});

test('another user devices are left alone', function (): void {
    RegisterDeviceTokenAction::run(PUSH_USER, 'mine', 'android');
    RegisterDeviceTokenAction::run('01JQZZZZZZZZZZZZZZZZZZZZZB', 'theirs', 'android');

    $this->mock(PushSenderInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('send')->once()->with('mine', Mockery::any())->andReturnTrue();
        $mock->shouldNotReceive('send')->with('theirs', Mockery::any());
    });

    SendPushAction::run(PUSH_USER, pushMessage());
});

test('the platform filter keeps iOS devices out while APNs is missing', function (): void {
    RegisterDeviceTokenAction::run(PUSH_USER, 'android-phone', 'android');
    RegisterDeviceTokenAction::run(PUSH_USER, 'iphone', 'ios');

    $this->mock(PushSenderInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('send')->once()->with('android-phone', Mockery::any())->andReturnTrue();
    });

    // The iOS token is stored (it will work the day APNs is configured) but is
    // not sent to today.
    expect(SendPushAction::run(PUSH_USER, pushMessage(), platform: 'android'))->toBe(1);
});

test('a user with no device is silence, not an error', function (): void {
    $this->mock(PushSenderInterface::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('send');
    });

    expect(SendPushAction::run(PUSH_USER, pushMessage()))->toBe(0);
});

test('an undelivered push is counted but does not blow up', function (): void {
    RegisterDeviceTokenAction::run(PUSH_USER, 'stale-token', 'android');

    $this->mock(PushSenderInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('send')->andReturnFalse();
    });

    expect(SendPushAction::run(PUSH_USER, pushMessage()))->toBe(0);
});
