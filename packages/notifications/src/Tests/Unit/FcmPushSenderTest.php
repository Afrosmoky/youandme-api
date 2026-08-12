<?php

use Illuminate\Support\Facades\Http;
use Youandme\Notifications\Data\PushMessageData;
use Youandme\Notifications\Support\FcmPushSender;

/*
 | The real FCM adapter: the OAuth handshake and the message body, driven against
 | a faked Google. The service account is generated into a temp file here, so no
 | real key and no network are involved.
 */

afterEach(function (): void {
    $path = config('services.fcm.credentials');

    if (is_string($path) && str_starts_with($path, sys_get_temp_dir()) && file_exists($path)) {
        unlink($path);
    }
});

function configureFcm(): string
{
    $key = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => 2048,
    ]);

    openssl_pkey_export($key, $pem);

    $path = tempnam(sys_get_temp_dir(), 'fcm').'.json';
    file_put_contents($path, json_encode([
        'type' => 'service_account',
        'project_id' => 'ja-i-ty-mobile',
        'client_email' => 'push@ja-i-ty-mobile.iam.gserviceaccount.com',
        'private_key' => $pem,
    ]));

    config(['services.fcm.credentials' => $path]);

    return $path;
}

function fakeGoogle(int $sendStatus = 200): void
{
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.token', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::response(['name' => 'projects/ja-i-ty-mobile/messages/1'], $sendStatus),
    ]);
}

function testMessage(): PushMessageData
{
    return new PushMessageData(title: 'Kredyt', body: 'Nagroda na koncie.', data: ['type' => 'ad_reward_granted']);
}

test('a push is exchanged for a token and delivered as a data message', function (): void {
    configureFcm();
    fakeGoogle();

    expect((new FcmPushSender)->send('device-token', testMessage()))->toBeTrue();

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'oauth2.googleapis.com')) {
            return false;
        }

        return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && is_string($request['assertion']) && substr_count($request['assertion'], '.') === 2;
    });

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'fcm.googleapis.com')) {
            return false;
        }

        $message = $request['message'];

        return $request->hasHeader('Authorization', 'Bearer ya29.token')
            // The project comes out of the key file, not from a second setting.
            && str_contains($request->url(), 'projects/ja-i-ty-mobile/messages:send')
            && $message['token'] === 'device-token'
            // No top-level notification block: it would make the Android SDK
            // render, on top of what notifee already draws from the data.
            && ! isset($message['notification'])
            && $message['data']['title'] === 'Kredyt'
            && $message['data']['type'] === 'ad_reward_granted'
            && $message['android']['priority'] === 'HIGH';
    });
});

test('iOS gets an APNs alert with the same copy, and the data payload travels with it', function (): void {
    configureFcm();
    fakeGoogle();

    $message = new PushMessageData(
        title: 'Rok temu',
        body: 'Wspomnienie sprzed roku czeka.',
        data: ['type' => 'memory_anniversary', 'memory_ulid' => '01JQZZZZZZZZZZZZZZZZZZZZZA', 'years' => '1'],
    );

    expect((new FcmPushSender)->send('iphone-token', $message))->toBeTrue();

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'fcm.googleapis.com')) {
            return false;
        }

        $sent = $request['message'];

        // The alert iOS draws itself, built from the same title and body notifee
        // renders on Android.
        $alertIsTheSameCopy = $sent['apns']['payload']['aps']['alert']['title'] === 'Rok temu'
            && $sent['apns']['payload']['aps']['alert']['body'] === 'Wspomnienie sprzed roku czeka.'
            && $sent['apns']['headers']['apns-push-type'] === 'alert'
            && $sent['apns']['headers']['apns-priority'] === '10';

        // Still sent as data as well: this is what the tap routes on, and losing
        // it would leave iOS with a notification that opens nothing in particular.
        $payloadRidesAlong = $sent['data']['type'] === 'memory_anniversary'
            && $sent['data']['memory_ulid'] === '01JQZZZZZZZZZZZZZZZZZZZZZA'
            && $sent['data']['years'] === '1'
            && $sent['data']['title'] === 'Rok temu';

        // Android is untouched by any of it: data-only, high priority, and no
        // notification block that would double up with notifee.
        $androidIsUnchanged = $sent['android'] === ['priority' => 'HIGH']
            && ! isset($sent['notification']);

        return $alertIsTheSameCopy && $payloadRidesAlong && $androidIsUnchanged;
    });
});

test('the access token is reused across pushes', function (): void {
    configureFcm();
    fakeGoogle();

    $sender = new FcmPushSender;
    $sender->send('device-a', testMessage());
    $sender->send('device-b', testMessage());

    // One handshake, two messages.
    Http::assertSentCount(3);
});

test('a rejected message is reported as undelivered, not thrown', function (): void {
    configureFcm();
    fakeGoogle(sendStatus: 404);

    expect((new FcmPushSender)->send('stale-token', testMessage()))->toBeFalse();
});

test('a refused token exchange is reported as undelivered', function (): void {
    configureFcm();
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 400)]);

    expect((new FcmPushSender)->send('device-token', testMessage()))->toBeFalse();
});

test('an unconfigured service account skips the push instead of failing', function (): void {
    config(['services.fcm.credentials' => null]);
    Http::fake();

    // A webhook answering an ad network must not break because push is not set up
    // — the state of every environment until Piotr points at the key file.
    expect((new FcmPushSender)->send('device-token', testMessage()))->toBeFalse();

    Http::assertNothingSent();
});

test('a missing or unusable key file skips the push', function (): void {
    Http::fake();

    config(['services.fcm.credentials' => 'storage/app/firebase/nie-ma-takiego-pliku.json']);
    expect((new FcmPushSender)->send('device-token', testMessage()))->toBeFalse();

    $path = tempnam(sys_get_temp_dir(), 'fcm').'.json';
    file_put_contents($path, '{"type":"service_account"}');
    config(['services.fcm.credentials' => $path]);

    // Present but not a key — same outcome, still no exception.
    expect((new FcmPushSender)->send('device-token', testMessage()))->toBeFalse();

    Http::assertNothingSent();
});
