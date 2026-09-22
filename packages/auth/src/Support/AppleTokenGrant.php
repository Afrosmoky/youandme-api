<?php

namespace Youandme\Auth\Support;

/**
 * What Apple hands back for an authorization code: the refresh token we revoke,
 * and the ID token that says whose it is.
 */
final readonly class AppleTokenGrant
{
    public function __construct(
        public string $refreshToken,
        public string $idToken,
    ) {}
}
