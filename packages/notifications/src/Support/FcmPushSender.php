<?php

namespace Youandme\Notifications\Support;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use Youandme\Notifications\Data\PushMessageData;

/**
 * Sends pushes through FCM HTTP v1.
 *
 * Hand-rolled OAuth instead of an SDK (Piotr's call): the flow is a service
 * account JWT exchanged for an access token, which firebase/php-jwt — already in
 * the project for Apple sign-in — does in a few lines. One less dependency, and
 * the whole thing hides behind PushSenderInterface anyway.
 *
 * Credentials come from the service account JSON on disk (path in
 * services.fcm.credentials), not from three env values: a PEM private key does
 * not belong in .env, and the file is gitignored under storage/.
 *
 * Data-only messages, deliberately: the client renders the notification itself
 * with notifee, which keeps one code path for local and remote notifications and
 * lets the app decide what to do while it is in the foreground.
 *
 * Nothing here throws. Missing configuration, an expired key, a dead network —
 * all logged and reported as "not delivered", because the caller is usually a
 * webhook that must still answer its ad network.
 */
final class FcmPushSender implements PushSenderInterface
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const JWT_GRANT = 'urn:ietf:params:oauth:grant-type:jwt-bearer';

    private const CACHE_KEY = 'notifications:fcm-access-token';

    /** Google's tokens live an hour; refresh a little early. */
    private const TOKEN_TTL_SECONDS = 3300;

    public function send(string $deviceToken, PushMessageData $message): bool
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return false;
        }

        $accessToken = $this->accessToken($credentials);

        if ($accessToken === null) {
            return false;
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                    'message' => [
                        'token' => $deviceToken,
                        // Title and body travel as data too — notifee builds the
                        // notification from them on the device.
                        'data' => [
                            'title' => $message->title,
                            'body' => $message->body,
                        ] + $message->data,
                        'android' => ['priority' => 'HIGH'],
                    ],
                ]);
        } catch (Throwable $exception) {
            Log::warning('FCM push failed', ['error' => $exception->getMessage()]);

            return false;
        }

        if ($response->failed()) {
            Log::warning('FCM push rejected', [
                'status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);

            return false;
        }

        return true;
    }

    /**
     * The service account, or null when it is not configured or not readable.
     *
     * @return array{project_id: string, client_email: string, private_key: string}|null
     */
    private function credentials(): ?array
    {
        $configured = config('services.fcm.credentials');

        if (! is_string($configured) || trim($configured) === '') {
            Log::warning('FCM push skipped: no service account configured');

            return null;
        }

        $path = str_starts_with($configured, '/') ? $configured : base_path($configured);

        if (! is_readable($path)) {
            Log::warning('FCM push skipped: service account file unreadable', ['path' => $configured]);

            return null;
        }

        $contents = file_get_contents($path);
        $decoded = $contents === false ? null : json_decode($contents, true);

        if (! is_array($decoded)
            || ! isset($decoded['project_id'], $decoded['client_email'], $decoded['private_key'])
            || ! is_string($decoded['project_id'])
            || ! is_string($decoded['client_email'])
            || ! is_string($decoded['private_key'])
        ) {
            Log::warning('FCM push skipped: service account file is not a usable key');

            return null;
        }

        return [
            'project_id' => $decoded['project_id'],
            'client_email' => $decoded['client_email'],
            'private_key' => $decoded['private_key'],
        ];
    }

    /**
     * A cached OAuth access token, or null when one cannot be obtained.
     *
     * @param  array{project_id: string, client_email: string, private_key: string}  $credentials
     */
    private function accessToken(array $credentials): ?string
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_string($cached)) {
            return $cached;
        }

        $assertion = $this->assertion($credentials);

        if ($assertion === null) {
            return null;
        }

        try {
            $response = Http::timeout(10)->asForm()->post(self::TOKEN_URL, [
                'grant_type' => self::JWT_GRANT,
                'assertion' => $assertion,
            ]);
        } catch (Throwable $exception) {
            Log::warning('FCM access token request failed', ['error' => $exception->getMessage()]);

            return null;
        }

        $token = $response->successful() ? $response->json('access_token') : null;

        if (! is_string($token) || $token === '') {
            Log::warning('FCM access token request rejected', ['status' => $response->status()]);

            return null;
        }

        // Only a working token is cached — an outage must not lock pushes out for
        // the next hour (the same rule as the AdMob verifier keys).
        Cache::put(self::CACHE_KEY, $token, self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * The signed JWT that buys an access token.
     *
     * @param  array{project_id: string, client_email: string, private_key: string}  $credentials
     */
    private function assertion(array $credentials): ?string
    {
        $issuedAt = time();

        try {
            return JWT::encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $issuedAt,
                'exp' => $issuedAt + 3600,
            ], $credentials['private_key'], 'RS256');
        } catch (Throwable $exception) {
            Log::warning('FCM assertion could not be signed', ['error' => $exception->getMessage()]);

            return null;
        }
    }
}
