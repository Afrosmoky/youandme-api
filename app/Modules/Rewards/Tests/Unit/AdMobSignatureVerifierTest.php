<?php

use App\Modules\Rewards\Exceptions\AdMobKeysUnavailableException;
use App\Modules\Rewards\Support\AdMobSignatureVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
 | The real verifier, exercised against a key pair generated here — no network and
 | no Google account needed. If ext-openssl ever behaves differently on the ECDSA
 | path, these are the tests that catch it.
 */

/**
 * @return array{pem: string, sign: Closure(string): string}
 */
function admobTestKey(): array
{
    $key = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
        // Ignored for EC, but this OpenSSL build refuses a zero length outright.
        'private_key_bits' => 384,
    ]);

    $details = openssl_pkey_get_details($key);

    return [
        'pem' => $details['key'],
        // Google sends the DER signature web-safe base64'd and unpadded.
        'sign' => function (string $data) use ($key): string {
            openssl_sign($data, $der, $key, OPENSSL_ALGO_SHA256);

            return rtrim(strtr(base64_encode($der), '+/', '-_'), '=');
        },
    ];
}

function fakeVerifierKeys(string $pem, int $keyId = 1234567890): void
{
    Http::fake([
        'gstatic.com/*' => Http::response(['keys' => [['keyId' => $keyId, 'pem' => $pem]]]),
    ]);
}

test('a signature made with the published key verifies', function (): void {
    $key = admobTestKey();
    fakeVerifierKeys($key['pem']);

    $data = 'ad_network=5450213213286189855&reward_amount=1&custom_data=abc&timestamp=1690000000';

    expect((new AdMobSignatureVerifier)->verify($data, $key['sign']($data), '1234567890'))->toBeTrue();
});

test('tampering with the signed data invalidates the signature', function (): void {
    $key = admobTestKey();
    fakeVerifierKeys($key['pem']);

    $signature = $key['sign']('reward_amount=1&custom_data=abc');

    // The classic attack: replay a real callback with a different nonce attached.
    expect((new AdMobSignatureVerifier)->verify('reward_amount=1&custom_data=xyz', $signature, '1234567890'))
        ->toBeFalse();
});

test('a signature from a foreign key is rejected', function (): void {
    $published = admobTestKey();
    $attacker = admobTestKey();
    fakeVerifierKeys($published['pem']);

    $data = 'reward_amount=1&custom_data=abc';

    expect((new AdMobSignatureVerifier)->verify($data, $attacker['sign']($data), '1234567890'))->toBeFalse();
});

test('an unknown key id is rejected after a refetch', function (): void {
    $key = admobTestKey();
    fakeVerifierKeys($key['pem'], keyId: 111);

    $data = 'reward_amount=1';

    expect((new AdMobSignatureVerifier)->verify($data, $key['sign']($data), '999'))->toBeFalse();

    // Rotation is the likely cause of a miss, so the keys are pulled again before
    // giving up: once for the cache fill, once for the retry.
    Http::assertSentCount(2);
});

test('garbage in place of a signature is rejected, not fatal', function (): void {
    $key = admobTestKey();
    fakeVerifierKeys($key['pem']);

    $verifier = new AdMobSignatureVerifier;

    expect($verifier->verify('reward_amount=1', 'not-base64-!!!', '1234567890'))->toBeFalse()
        ->and($verifier->verify('reward_amount=1', '', '1234567890'))->toBeFalse();
});

test('the key document is fetched once and reused', function (): void {
    $key = admobTestKey();
    fakeVerifierKeys($key['pem']);

    $data = 'reward_amount=1';
    $signature = $key['sign']($data);
    $verifier = new AdMobSignatureVerifier;

    $verifier->verify($data, $signature, '1234567890');
    $verifier->verify($data, $signature, '1234567890');

    Http::assertSentCount(1);
});

test('an outage is signalled as undecidable, not as an invalid signature', function (): void {
    Http::fake(['gstatic.com/*' => Http::response('', 500)]);

    // False would mean "not genuine" — a final verdict that costs a real reward.
    expect(fn () => (new AdMobSignatureVerifier)->verify('reward_amount=1', 'c2ln', '1234567890'))
        ->toThrow(AdMobKeysUnavailableException::class);

    // And nothing is remembered, so the next callback tries Google again instead
    // of being stuck with "no keys" for a day.
    expect(Cache::get('rewards:admob-verifier-keys'))->toBeNull();
});

test('an unreachable key endpoint is undecidable too', function (): void {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    expect(fn () => (new AdMobSignatureVerifier)->verify('reward_amount=1', 'c2ln', '1234567890'))
        ->toThrow(AdMobKeysUnavailableException::class);
});

test('a key document with nothing usable in it is undecidable', function (): void {
    Http::fake(['gstatic.com/*' => Http::response(['keys' => []])]);

    expect(fn () => (new AdMobSignatureVerifier)->verify('reward_amount=1', 'c2ln', '1234567890'))
        ->toThrow(AdMobKeysUnavailableException::class)
        ->and(Cache::get('rewards:admob-verifier-keys'))->toBeNull();
});
