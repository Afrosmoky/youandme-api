<?php

namespace App\Modules\Rewards\Support;

use App\Modules\Rewards\Exceptions\AdMobKeysUnavailableException;

/**
 * Verifies that an AdMob SSV callback really came from Google.
 *
 * An Adapter over an external service, not a domain Service — the same category
 * as Auth's GoogleTokenVerifierInterface, and the reason it may exist despite
 * "no services": the concrete side talks HTTP and OpenSSL, the tests bind a fake.
 */
interface AdMobSignatureVerifierInterface
{
    /**
     * False means "this callback is not genuine" — a final verdict. When the
     * verdict cannot be reached at all (Google's keys unreachable), implementations
     * throw AdMobKeysUnavailableException instead of guessing, so the caller can
     * ask for a redelivery rather than silently dropping a real reward.
     *
     * @param  string  $signedData  the raw callback query string up to (excluding) "&signature="
     * @param  string  $signature  base64url-encoded DER ECDSA signature from the callback
     * @param  string  $keyId  which of Google's public keys signed it
     *
     * @throws AdMobKeysUnavailableException
     */
    public function verify(string $signedData, string $signature, string $keyId): bool;
}
