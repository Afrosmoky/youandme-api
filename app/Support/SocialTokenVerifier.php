<?php

namespace App\Support;

interface SocialTokenVerifier
{
    /**
     * Validate a provider ID token and return its normalized claims.
     *
     * @return array{sub: string, email: ?string, email_verified: bool, name: ?string}
     *
     * @throws SocialTokenException when the token is invalid, expired, or has
     *                              the wrong issuer/audience.
     */
    public function verify(string $idToken): array;
}
