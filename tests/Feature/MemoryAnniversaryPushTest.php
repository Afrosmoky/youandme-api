<?php

use App\Modules\Memories\Models\Memory;
use Carbon\CarbonImmutable;
use Mockery\MockInterface;
use Youandme\Auth\Models\User;
use Youandme\Notifications\Actions\RegisterDeviceTokenAction;
use Youandme\Notifications\Data\PushMessageData;
use Youandme\Notifications\Support\PushSenderInterface;

/*
 | memories:notify-anniversaries end to end (P9 Slice B): the hourly scan finds a
 | memory whose anniversary is today, and the app layer walks couple → members →
 | devices. FCM itself is faked; what is under test is the wiring and, above all,
 | the timing — a push that arrives at three in the morning is a bug.
 */

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function atLocalTime(string $localTime, string $timezone): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse($localTime, $timezone));
}

function coupleWithPhone(string $timezone, string $token = 'phone-token', string $platform = 'android'): User
{
    $user = createUserWithCouple(['timezone' => $timezone]);
    RegisterDeviceTokenAction::run($user->ulid, $token, $platform);

    return $user;
}

function memoryAnsweredAt(User $user, string $localTime, string $timezone): Memory
{
    return Memory::factory()->for($user)->create([
        'answered_at' => CarbonImmutable::parse($localTime, $timezone),
    ]);
}

function expectPush(callable $assert): void
{
    test()->mock(PushSenderInterface::class, function (MockInterface $mock) use ($assert): void {
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(fn (string $token, PushMessageData $message): bool => $assert($token, $message) !== false)
            ->andReturnTrue();
    });
}

function expectSilence(): void
{
    test()->mock(PushSenderInterface::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('send');
    });
}

test('a memory from exactly a year ago is announced at 18:00 local', function (): void {
    $user = coupleWithPhone('Europe/Warsaw');
    $memory = memoryAnsweredAt($user, '2026-05-20 12:00', 'Europe/Warsaw');
    atLocalTime('2027-05-20 18:15', 'Europe/Warsaw');

    expectPush(function (string $token, PushMessageData $message) use ($memory): bool {
        expect($token)->toBe('phone-token');
        expect($message->data)->toBe([
            'type' => 'memory_anniversary',
            'memory_ulid' => $memory->ulid,
            'kind' => 'year',
            'years' => '1',
        ]);
        // Copy comes from lang/pl with an explicit locale, so a missing APP_LOCALE
        // cannot turn the push into a translation key.
        expect($message->title)->toBe('Rok temu');
        expect($message->body)->toStartWith('Rok temu zapisaliście wspomnienie');

        return true;
    });

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});

test('a memory from a month ago is announced with the monthly copy', function (): void {
    $user = coupleWithPhone('Europe/Warsaw');
    memoryAnsweredAt($user, '2027-04-20 09:00', 'Europe/Warsaw');
    atLocalTime('2027-05-20 18:15', 'Europe/Warsaw');

    expectPush(function (string $token, PushMessageData $message): bool {
        expect($message->data['kind'])->toBe('month');
        expect($message->title)->toBe('Miesiąc temu');

        return true;
    });

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});

test('the yearly anniversary outranks the monthly one and only one push goes out', function (): void {
    $user = coupleWithPhone('Europe/Warsaw');
    memoryAnsweredAt($user, '2027-04-20 09:00', 'Europe/Warsaw');
    $yearOld = memoryAnsweredAt($user, '2026-05-20 09:00', 'Europe/Warsaw');
    atLocalTime('2027-05-20 18:15', 'Europe/Warsaw');

    expectPush(function (string $token, PushMessageData $message) use ($yearOld): bool {
        expect($message->data['memory_ulid'])->toBe($yearOld->ulid);
        expect($message->data['kind'])->toBe('year');

        return true;
    });

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});

test('two years old reads as two years, not one', function (): void {
    $user = coupleWithPhone('Europe/Warsaw');
    memoryAnsweredAt($user, '2025-05-20 09:00', 'Europe/Warsaw');
    atLocalTime('2027-05-20 18:15', 'Europe/Warsaw');

    expectPush(function (string $token, PushMessageData $message): bool {
        expect($message->data['years'])->toBe('2');
        expect($message->title)->toBe('2 lata temu');

        return true;
    });

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});

test('nothing goes out an hour too early', function (): void {
    $user = coupleWithPhone('Europe/Warsaw');
    memoryAnsweredAt($user, '2026-05-20 12:00', 'Europe/Warsaw');
    atLocalTime('2027-05-20 17:59', 'Europe/Warsaw');

    expectSilence();

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});

test('at one UTC instant only the couple whose local evening it is hears about it', function (): void {
    $auckland = coupleWithPhone('Pacific/Auckland', 'auckland-phone');
    $losAngeles = coupleWithPhone('America/Los_Angeles', 'la-phone');

    // Each couple has an anniversary on its OWN local today.
    memoryAnsweredAt($auckland, '2026-06-20 10:00', 'Pacific/Auckland');
    memoryAnsweredAt($losAngeles, '2026-06-19 10:00', 'America/Los_Angeles');

    // 18:30 in Auckland is 23:30 the previous evening in Los Angeles.
    atLocalTime('2027-06-20 18:30', 'Pacific/Auckland');

    expectPush(function (string $token): bool {
        expect($token)->toBe('auckland-phone');

        return true;
    });

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});

test('the same couple hears about it when its own evening comes round', function (): void {
    $auckland = coupleWithPhone('Pacific/Auckland', 'auckland-phone');
    $losAngeles = coupleWithPhone('America/Los_Angeles', 'la-phone');

    memoryAnsweredAt($auckland, '2026-06-20 10:00', 'Pacific/Auckland');
    memoryAnsweredAt($losAngeles, '2026-06-19 10:00', 'America/Los_Angeles');

    // Seven hours later: Los Angeles is at 18:30, Auckland is at lunchtime.
    atLocalTime('2027-06-19 18:30', 'America/Los_Angeles');

    expectPush(function (string $token): bool {
        expect($token)->toBe('la-phone');

        return true;
    });

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});

test('a repeated run in the same hour does not push twice', function (): void {
    $user = coupleWithPhone('Europe/Warsaw');
    memoryAnsweredAt($user, '2026-05-20 12:00', 'Europe/Warsaw');
    atLocalTime('2027-05-20 18:15', 'Europe/Warsaw');

    // The marker claims the couple's local day, so a manual re-run, a retry or an
    // overlapping schedule costs a scan and nothing else.
    expectPush(fn (): bool => true);

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});

test('both members of a couple are told', function (): void {
    $userA = createUserWithCouple(['timezone' => 'Europe/Warsaw']);
    $couple = activeCoupleOf($userA);

    $userB = User::factory()->create(['timezone' => 'Europe/Warsaw', 'active_couple_id' => $couple->id]);
    $couple->user_b_id = $userB->id;
    $couple->save();

    RegisterDeviceTokenAction::run($userA->ulid, 'phone-a', 'android');
    RegisterDeviceTokenAction::run($userB->ulid, 'phone-b', 'android');

    memoryAnsweredAt($userA, '2026-05-20 12:00', 'Europe/Warsaw');
    atLocalTime('2027-05-20 18:15', 'Europe/Warsaw');

    $this->mock(PushSenderInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('send')->once()->with('phone-a', Mockery::any())->andReturnTrue();
        $mock->shouldReceive('send')->once()->with('phone-b', Mockery::any())->andReturnTrue();
    });

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});

test('a memory the couple deleted has no anniversary', function (): void {
    $user = coupleWithPhone('Europe/Warsaw');
    memoryAnsweredAt($user, '2026-05-20 12:00', 'Europe/Warsaw')->delete();
    atLocalTime('2027-05-20 18:15', 'Europe/Warsaw');

    expectSilence();

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});

test('a couple without a registered device is silence, not a failure', function (): void {
    $user = createUserWithCouple(['timezone' => 'Europe/Warsaw']);
    memoryAnsweredAt($user, '2026-05-20 12:00', 'Europe/Warsaw');
    atLocalTime('2027-05-20 18:15', 'Europe/Warsaw');

    expectSilence();

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});

test('an iOS-only couple hears nothing until APNs exists', function (): void {
    $user = coupleWithPhone('Europe/Warsaw', 'iphone-token', 'ios');
    memoryAnsweredAt($user, '2026-05-20 12:00', 'Europe/Warsaw');
    atLocalTime('2027-05-20 18:15', 'Europe/Warsaw');

    expectSilence();

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});

test('a failing push does not fail the run', function (): void {
    $user = coupleWithPhone('Europe/Warsaw');
    memoryAnsweredAt($user, '2026-05-20 12:00', 'Europe/Warsaw');
    atLocalTime('2027-05-20 18:15', 'Europe/Warsaw');

    $this->mock(PushSenderInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('send')->andReturnFalse();
    });

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});

test('a memory from the 31st is caught on the last day of a shorter month', function (): void {
    $user = coupleWithPhone('Europe/Warsaw');
    $memory = memoryAnsweredAt($user, '2027-01-31 20:00', 'Europe/Warsaw');

    // 28 February is the last day of the month, so January's 28th-31st all have
    // their monthly anniversary today — nothing falls out of the calendar.
    atLocalTime('2027-02-28 18:15', 'Europe/Warsaw');

    expectPush(function (string $token, PushMessageData $message) use ($memory): bool {
        expect($message->data['memory_ulid'])->toBe($memory->ulid);
        expect($message->data['kind'])->toBe('month');

        return true;
    });

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});

test('a couple with no anniversary today hears nothing', function (): void {
    $user = coupleWithPhone('Europe/Warsaw');
    memoryAnsweredAt($user, '2027-05-02 12:00', 'Europe/Warsaw');
    atLocalTime('2027-05-20 18:15', 'Europe/Warsaw');

    expectSilence();

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});

test('the anniversary scan ignores couples whose account is gone', function (): void {
    $user = coupleWithPhone('Europe/Warsaw');
    memoryAnsweredAt($user, '2026-05-20 12:00', 'Europe/Warsaw');
    // A soft-deleted member leaves a couple with no timezone and nobody to tell.
    $user->delete();
    atLocalTime('2027-05-20 18:15', 'Europe/Warsaw');

    expectSilence();

    $this->artisan('memories:notify-anniversaries')->assertSuccessful();
});
