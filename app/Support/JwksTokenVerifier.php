<?php

namespace App\Support;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Shared OAuth ID-token verification: fetch the provider JWKs, verify the
 * signature/expiry with firebase/php-jwt, then check issuer and audience.
 * Concrete subclasses supply the provider specifics.
 */
abstract class JwksTokenVerifier implements SocialTokenVerifier
{
    abstract protected function jwksUrl(): string;

    /** @return list<string> */
    abstract protected function allowedIssuers(): array;

    abstract protected function audience(): ?string;

    /**
     * @return array{sub: string, email: ?string, email_verified: bool, name: ?string}
     */
    abstract protected function mapClaims(object $claims): array;

    public function verify(string $idToken): array
    {
        $audience = $this->audience();

        if ($audience === null || $audience === '') {
            throw new SocialTokenException('Social sign-in is not configured.');
        }

        try {
            $claims = JWT::decode($idToken, JWK::parseKeySet($this->fetchJwks()));
        } catch (Throwable $e) {
            throw new SocialTokenException('Invalid ID token: '.$e->getMessage(), previous: $e);
        }

        if (! in_array($claims->iss ?? '', $this->allowedIssuers(), true)) {
            throw new SocialTokenException('Unexpected token issuer.');
        }

        if (($claims->aud ?? null) !== $audience) {
            throw new SocialTokenException('Unexpected token audience.');
        }

        return $this->mapClaims($claims);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchJwks(): array
    {
        return Cache::remember(
            'social-jwks:'.static::class,
            now()->addHours(6),
            fn (): array => Http::get($this->jwksUrl())->throw()->json()
        );
    }
}
