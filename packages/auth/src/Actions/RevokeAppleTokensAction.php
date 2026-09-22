<?php

namespace Youandme\Auth\Actions;

use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;
use Youandme\Auth\Support\AppleAuthClientInterface;
use Youandme\Auth\Support\AppleAuthException;
use Youandme\Auth\Support\AppleTokenVerifierInterface;
use Youandme\Auth\Support\SocialTokenException;

/**
 * Revoke our Sign in with Apple access for a user who is deleting their account
 * (App Store guideline 5.1.1(v)).
 *
 * We only ever received an identity token at sign-in, so there is nothing stored
 * to revoke. The app therefore signs in with Apple again right before deleting
 * and sends the fresh authorization code; we trade it for a refresh token and
 * revoke that.
 *
 * Best-effort by contract: a missing code, a missing key or a failing Apple never
 * stops the deletion. Each is a Log::warning, and none of them carries the code,
 * a token or an identifier.
 *
 * The code must belong to this account. The phone may be signed in to a
 * different Apple ID than the one the account was created with; revoking then
 * would cut our app off from somebody else's Apple ID. So the sub of the ID token
 * from the exchange has to equal users.apple_id. Otherwise nothing is revoked.
 *
 * Returns whether the revocation went through.
 */
final class RevokeAppleTokensAction
{
    use AsAction;

    public function __construct(
        private readonly AppleAuthClientInterface $apple,
        private readonly AppleTokenVerifierInterface $verifier,
    ) {}

    public function handle(User $user, ?string $authorizationCode): bool
    {
        if ($user->apple_id === null) {
            return false;
        }

        if ($authorizationCode === null || $authorizationCode === '') {
            Log::warning('Apple token revocation skipped: no authorization code sent');

            return false;
        }

        if (! $this->apple->isConfigured()) {
            Log::warning('Apple token revocation skipped: Sign in with Apple key not configured');

            return false;
        }

        try {
            $grant = $this->apple->exchangeAuthorizationCode($authorizationCode);
        } catch (AppleAuthException $exception) {
            Log::warning('Apple token revocation failed at code exchange', ['reason' => $exception->getMessage()]);

            return false;
        }

        try {
            $sub = $this->verifier->verify($grant->idToken)['sub'];
        } catch (SocialTokenException) {
            Log::warning('Apple token revocation skipped: ID token from the code exchange did not verify');

            return false;
        }

        if ($sub !== $user->apple_id) {
            Log::warning('Apple token revocation skipped: authorization code belongs to a different Apple ID');

            return false;
        }

        try {
            $this->apple->revokeRefreshToken($grant->refreshToken);
        } catch (AppleAuthException $exception) {
            Log::warning('Apple token revocation failed', ['reason' => $exception->getMessage()]);

            return false;
        }

        return true;
    }
}
