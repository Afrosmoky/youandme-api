<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Youandme\Auth\Support\AppleAuthClient;
use Youandme\Auth\Support\AppleAuthException;

/** A throwaway P-256 key standing in for the .p8; returns its public half. */
function configureAppleKey(): string
{
    // private_key_bits means nothing for EC, but some OpenSSL 3 builds refuse to
    // generate any key while the configured default length is 0.
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'private_key_bits' => 384]);
    openssl_pkey_export($key, $privatePem);
    $path = tempnam(sys_get_temp_dir(), 'apple-key');
    file_put_contents($path, $privatePem);

    config([
        'services.apple.client_id' => 'app.jaity.mobile',
        'services.apple.team_id' => 'TEAM123456',
        'services.apple.key_id' => 'KEY1234567',
        'services.apple.private_key_path' => $path,
    ]);

    return openssl_pkey_get_details($key)['key'];
}

afterEach(function (): void {
    $path = (string) config('services.apple.private_key_path');
    if (str_starts_with($path, sys_get_temp_dir()) && is_file($path)) {
        unlink($path);
    }
});

test('it is configured only when all of the key settings are present', function (): void {
    configureAppleKey();
    expect(app(AppleAuthClient::class)->isConfigured())->toBeTrue();

    config(['services.apple.key_id' => null]);
    expect(app(AppleAuthClient::class)->isConfigured())->toBeFalse();
});

test('the code exchange is authenticated with an ES256 client secret', function (): void {
    $publicKey = configureAppleKey();
    Http::fake(['appleid.apple.com/auth/token' => Http::response(['refresh_token' => 'r-token', 'id_token' => 'id-token', 'access_token' => 'a'])]);

    $grant = app(AppleAuthClient::class)->exchangeAuthorizationCode('the-code');

    expect($grant->refreshToken)->toBe('r-token')->and($grant->idToken)->toBe('id-token');

    Http::assertSent(function (Request $request) use ($publicKey): bool {
        $secret = $request['client_secret'];
        $claims = JWT::decode($secret, new Key($publicKey, 'ES256'));
        $header = json_decode(base64_decode(explode('.', $secret)[0]), true);

        return $request->url() === 'https://appleid.apple.com/auth/token'
            && $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'the-code'
            && $request['client_id'] === 'app.jaity.mobile'
            && $header['kid'] === 'KEY1234567'
            && $claims->iss === 'TEAM123456'
            && $claims->sub === 'app.jaity.mobile'
            && $claims->aud === 'https://appleid.apple.com'
            && $claims->exp - $claims->iat <= 300;
    });
});

test('the revocation names the refresh token', function (): void {
    configureAppleKey();
    Http::fake(['appleid.apple.com/auth/revoke' => Http::response()]);

    app(AppleAuthClient::class)->revokeRefreshToken('r-token');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://appleid.apple.com/auth/revoke'
        && $request['token'] === 'r-token'
        && $request['token_type_hint'] === 'refresh_token');
});

test('an Apple error becomes an exception with the status and error code only', function (): void {
    configureAppleKey();
    Http::fake(['appleid.apple.com/auth/token' => Http::response(['error' => 'invalid_grant', 'refresh_token' => 'leak'], 400)]);

    expect(fn () => app(AppleAuthClient::class)->exchangeAuthorizationCode('the-code'))
        ->toThrow(AppleAuthException::class, 'Apple /auth/token failed with HTTP 400 (invalid_grant).');
});

test('a reply without the tokens is an error', function (): void {
    configureAppleKey();
    Http::fake(['appleid.apple.com/auth/token' => Http::response(['access_token' => 'a'])]);

    expect(fn () => app(AppleAuthClient::class)->exchangeAuthorizationCode('the-code'))
        ->toThrow(AppleAuthException::class);
});

test('an unreadable key is an error, not a crash', function (): void {
    configureAppleKey();
    config(['services.apple.private_key_path' => '/nonexistent/AuthKey.p8']);
    Http::fake();

    expect(fn () => app(AppleAuthClient::class)->exchangeAuthorizationCode('the-code'))
        ->toThrow(AppleAuthException::class, 'Apple private key is not readable.');
    Http::assertNothingSent();
});
