<?php

namespace Youandme\Auth\Support;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Apple's /auth/token and /auth/revoke, authenticated with a client_secret we
 * sign ourselves: a short-lived ES256 JWT made from the Sign in with Apple key
 * (.p8) and identified by the team and key ids. See services.apple.
 *
 * Every failure becomes an AppleAuthException carrying only a status and Apple's
 * `error` code. Laravel's RequestException quotes the response body, and the body
 * of a successful-looking /auth/token reply is exactly the tokens we must not log.
 */
final class AppleAuthClient implements AppleAuthClientInterface
{
    private const BASE_URL = 'https://appleid.apple.com';

    /** Seconds. Account deletion waits for these calls; Apple answers in well under that. */
    private const TIMEOUT = 5;

    /** The client secret only has to outlive the two requests it signs. */
    private const CLIENT_SECRET_TTL = 300;

    public function isConfigured(): bool
    {
        return filled(config('services.apple.client_id'))
            && filled(config('services.apple.team_id'))
            && filled(config('services.apple.key_id'))
            && filled(config('services.apple.private_key_path'));
    }

    public function exchangeAuthorizationCode(string $authorizationCode): AppleTokenGrant
    {
        $response = $this->post('/auth/token', [
            'grant_type' => 'authorization_code',
            'code' => $authorizationCode,
        ]);

        $refreshToken = $response->json('refresh_token');
        $idToken = $response->json('id_token');

        if (! is_string($refreshToken) || $refreshToken === '' || ! is_string($idToken) || $idToken === '') {
            throw new AppleAuthException('Apple token response lacks refresh_token or id_token.');
        }

        return new AppleTokenGrant($refreshToken, $idToken);
    }

    public function revokeRefreshToken(string $refreshToken): void
    {
        $this->post('/auth/revoke', [
            'token' => $refreshToken,
            'token_type_hint' => 'refresh_token',
        ]);
    }

    /**
     * @param  array<string, string>  $form
     */
    private function post(string $path, array $form): Response
    {
        $clientId = (string) config('services.apple.client_id');

        try {
            $response = Http::asForm()
                ->timeout(self::TIMEOUT)
                ->post(self::BASE_URL.$path, $form + [
                    'client_id' => $clientId,
                    'client_secret' => $this->clientSecret($clientId),
                ]);
        } catch (ConnectionException) {
            throw new AppleAuthException("Apple {$path} unreachable.");
        }

        if (! $response->successful()) {
            $error = $response->json('error');

            throw new AppleAuthException(sprintf(
                'Apple %s failed with HTTP %d%s.',
                $path,
                $response->status(),
                is_string($error) ? " ({$error})" : '',
            ));
        }

        return $response;
    }

    private function clientSecret(string $clientId): string
    {
        $configured = (string) config('services.apple.private_key_path');
        $path = str_starts_with($configured, '/') ? $configured : base_path($configured);

        $privateKey = is_readable($path) ? file_get_contents($path) : false;

        if ($privateKey === false || $privateKey === '') {
            throw new AppleAuthException('Apple private key is not readable.');
        }

        $now = time();

        try {
            return JWT::encode([
                'iss' => (string) config('services.apple.team_id'),
                'iat' => $now,
                'exp' => $now + self::CLIENT_SECRET_TTL,
                'aud' => self::BASE_URL,
                'sub' => $clientId,
            ], $privateKey, 'ES256', (string) config('services.apple.key_id'));
        } catch (Throwable $exception) {
            // The class name only: an OpenSSL message is harmless, but nothing
            // derived from the key material belongs in a log.
            throw new AppleAuthException('Apple client secret could not be signed ('.class_basename($exception).').');
        }
    }
}
