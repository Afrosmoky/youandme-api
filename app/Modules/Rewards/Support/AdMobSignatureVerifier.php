<?php

namespace App\Modules\Rewards\Support;

use App\Modules\Rewards\Exceptions\AdMobKeysUnavailableException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Checks an AdMob SSV callback against Google's published verifier keys.
 *
 * The scheme (Google's, not ours): the callback is a GET whose query string ends
 * with &signature=...&key_id=...; everything BEFORE "&signature=" is the signed
 * payload, the signature is a base64url-encoded DER ECDSA signature over it, and
 * the public keys live in a small JSON document keyed by key_id.
 *
 * No new dependency: ext-openssl verifies ECDSA/SHA-256 natively, so this is a
 * ~60-line adapter instead of an SDK (confirmed with Piotr).
 *
 * Key handling is deliberately conservative:
 * - keys are cached for a day (the document is small but the webhook is hot),
 * - a FAILED fetch is never cached, so an outage cannot pin us to "no keys",
 * - a failed fetch THROWS instead of returning false: "we cannot tell" is not
 *   "not genuine", and answering the callback with a final verdict would drop a
 *   real reward (see AdMobKeysUnavailableException),
 * - an unknown key_id triggers exactly one refetch before rejecting, because
 *   Google rotates keys and a miss is more likely rotation than forgery.
 */
final class AdMobSignatureVerifier implements AdMobSignatureVerifierInterface
{
    private const KEYS_URL = 'https://gstatic.com/admob/reward/verifier-keys.json';

    private const CACHE_KEY = 'rewards:admob-verifier-keys';

    private const CACHE_TTL_SECONDS = 86400;

    public function verify(string $signedData, string $signature, string $keyId): bool
    {
        $publicKey = $this->publicKey($keyId);
        $derSignature = $this->decodeSignature($signature);

        if ($publicKey === null || $derSignature === null || $signedData === '') {
            return false;
        }

        return openssl_verify($signedData, $derSignature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    private function publicKey(string $keyId): ?string
    {
        $keys = $this->keys(refresh: false);

        if (isset($keys[$keyId])) {
            return $keys[$keyId];
        }

        // Unknown id: refetch once before calling a possibly genuine callback a fake.
        return $this->keys(refresh: true)[$keyId] ?? null;
    }

    /**
     * @return array<string, string> key id => PEM public key
     *
     * @throws AdMobKeysUnavailableException
     */
    private function keys(bool $refresh): array
    {
        if (! $refresh) {
            $cached = Cache::get(self::CACHE_KEY);

            if (is_array($cached)) {
                /** @var array<string, string> $cached */
                return $cached;
            }
        }

        // Only a usable document reaches this line — a failure throws, so nothing
        // negative is ever written to the cache.
        $fetched = $this->fetchKeys();

        Cache::put(self::CACHE_KEY, $fetched, self::CACHE_TTL_SECONDS);

        return $fetched;
    }

    /**
     * @return array<string, string>
     *
     * @throws AdMobKeysUnavailableException
     */
    private function fetchKeys(): array
    {
        try {
            $response = Http::timeout(5)->get(self::KEYS_URL);
        } catch (Throwable $exception) {
            Log::warning('AdMob verifier keys unreachable', ['error' => $exception->getMessage()]);

            throw new AdMobKeysUnavailableException('AdMob verifier keys unreachable', previous: $exception);
        }

        if ($response->failed()) {
            Log::warning('AdMob verifier keys request failed', ['status' => $response->status()]);

            throw new AdMobKeysUnavailableException("AdMob verifier keys request failed with {$response->status()}");
        }

        $keys = [];

        /** @var array<int, array{keyId?: int|string, pem?: string}> $entries */
        $entries = $response->json('keys') ?? [];

        foreach ($entries as $entry) {
            if (isset($entry['keyId'], $entry['pem'])) {
                $keys[(string) $entry['keyId']] = $entry['pem'];
            }
        }

        // A 200 with no usable key is upstream breakage, not a verdict — same
        // treatment as an unreachable endpoint.
        if ($keys === []) {
            Log::warning('AdMob verifier keys document carried no usable key');

            throw new AdMobKeysUnavailableException('AdMob verifier keys document carried no usable key');
        }

        return $keys;
    }

    private function decodeSignature(string $signature): ?string
    {
        if ($signature === '') {
            return null;
        }

        // Web-safe base64, usually unpadded.
        $normalized = strtr($signature, '-_', '+/');
        $padded = str_pad($normalized, intdiv(strlen($normalized) + 3, 4) * 4, '=');

        $decoded = base64_decode($padded, true);

        return $decoded === false || $decoded === '' ? null : $decoded;
    }
}
