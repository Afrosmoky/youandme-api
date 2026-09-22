<?php

namespace Youandme\Auth\Support;

/**
 * Adapter over Apple's Sign in with Apple REST API — the two calls account
 * deletion needs to revoke our access (App Store guideline 5.1.1(v)). Bound in
 * AuthServiceProvider, mocked in tests.
 */
interface AppleAuthClientInterface
{
    /** Whether the Sign in with Apple key is configured at all. */
    public function isConfigured(): bool;

    /**
     * Exchange a one-time authorization code (valid for about five minutes) for
     * tokens.
     *
     * @throws AppleAuthException
     */
    public function exchangeAuthorizationCode(string $authorizationCode): AppleTokenGrant;

    /**
     * @throws AppleAuthException
     */
    public function revokeRefreshToken(string $refreshToken): void;
}
