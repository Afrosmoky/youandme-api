<?php

namespace Youandme\Auth\Actions\Concerns;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Youandme\Auth\Data\AuthResult;
use Youandme\Auth\Data\UserData;
use Youandme\Auth\Data\UserRegisteredData;
use Youandme\Auth\Events\UserRegistered;
use Youandme\Auth\Models\User;
use Youandme\Auth\Rules\ValidNickname;
use Youandme\Auth\Support\SocialTokenException;
use Youandme\Auth\Support\SocialTokenVerifier;

/**
 * Shared find-or-create flow for social sign-in (Google, Apple). Verifies the
 * provider token, then issues a Sanctum token for the matching or newly created
 * user. `isNewUser` on the result drives the controller's 201-vs-200 status.
 *
 * Matching an existing account is the security-critical part of this flow, because
 * finding an account IS signing into it — the Sanctum token is issued either way,
 * whether or not the provider id gets attached. Two rules guard it, and both refuse
 * rather than guess:
 *
 *   - an address the provider has not verified never matches anything (422),
 *   - an account already tied to a different id at the same provider is never
 *     signed into (409).
 *
 * Neither rule can be relaxed on the grounds that "the providers behave well today".
 * Google documents email_verified as the precondition for treating the address as an
 * identifier, and whether it ever ships false is their policy to change, not ours to
 * rely on. The audience check does not help here either: an attacker signing in
 * through our own app holds a perfectly valid token issued for us.
 */
trait ResolvesSocialSignIn
{
    protected function signIn(SocialTokenVerifier $verifier, string $idToken, string $providerColumn): AuthResult
    {
        try {
            $payload = $verifier->verify($idToken);
        } catch (SocialTokenException $e) {
            // The verifier's own words ("Expired token", "Signature verification
            // failed", "Social sign-in is not configured.") describe our internals
            // to whoever is holding the token — including someone probing it. They
            // belong in the log, not in the response.
            Log::warning('Social sign-in token rejected.', [
                'provider' => $this->providerName($providerColumn),
                'reason' => $e->getMessage(),
            ]);

            abort(Response::HTTP_UNAUTHORIZED, 'Nie udało się zweryfikować logowania.');
        }

        // The provider id is the strong identity: it is stable for the life of the
        // account and cannot be claimed by anyone else. Nothing below re-examines a
        // user found this way — in particular an unverified or absent address is
        // irrelevant once the id matches, which is exactly the Apple repeat sign-in.
        $user = User::where($providerColumn, $payload['sub'])->first();

        if ($user === null && $payload['email'] !== null) {
            $this->refuseUnverifiedEmail($payload, $providerColumn);

            $user = User::where('email', $payload['email'])->first();

            $this->refuseForeignProviderId($user, $payload, $providerColumn);
        }

        // Apple may omit the email on repeat sign-ins; for a first sign-in we
        // need it (the users.email column is not nullable).
        if ($user === null && $payload['email'] === null) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Do założenia konta potrzebny jest adres e-mail.');
        }

        $isNew = $user === null;

        // Pure Auth: create/update the user only. The couple (for a new user) is
        // created by the app composition root after this action returns.
        [$user, $token] = DB::transaction(function () use ($user, $payload, $providerColumn): array {
            if ($user === null) {
                $user = new User;
                $user->email = $payload['email'];
                $user->nickname = $this->generateNickname($payload['email']);
                $user->password = Str::random(40); // unusable; social users have no password
                $user->{$providerColumn} = $payload['sub'];

                if ($payload['email_verified']) {
                    $user->email_verified_at = now();
                }

                $user->save();
            } else {
                $dirty = false;

                if ($user->{$providerColumn} === null) {
                    $user->{$providerColumn} = $payload['sub'];
                    $dirty = true;
                }

                if ($payload['email_verified'] && $user->email_verified_at === null) {
                    $user->email_verified_at = now();
                    $dirty = true;
                }

                if ($dirty) {
                    $user->save();
                }
            }

            return [$user, $user->createToken('mobile')->plainTextToken];
        });

        if ($isNew) {
            UserRegistered::dispatch(UserRegisteredData::fromModel($user));
        }

        return new AuthResult(UserData::fromModel($user), $token, isNewUser: $isNew);
    }

    /**
     * An address the provider has not verified is not evidence of anything, so it
     * matches no account — and, deliberately, does not open one either.
     *
     * Refusing when the address is NOT yet in our database looks like overzealousness
     * and is not. Letting an unverified address create an account would lock out the
     * person who actually owns it: when they later arrive with their own, verified
     * provider account, their sub is different and the address is the same, so they
     * would meet the 409 below and stand locked out of their own address with no way
     * through. Refusing at the door is the only outcome that does not wall them in.
     *
     * @param  array{sub: string, email: ?string, email_verified: bool, name: ?string}  $payload
     */
    private function refuseUnverifiedEmail(array $payload, string $providerColumn): void
    {
        if ($payload['email_verified']) {
            return;
        }

        // A possible account takeover attempt, and worth seeing even when it is not:
        // the token itself is never logged, only who it claimed to be.
        Log::warning('Social sign-in refused: provider has not verified the address.', [
            'provider' => $this->providerName($providerColumn),
            'sub' => $payload['sub'],
            'email' => $payload['email'],
            'reason' => 'unverified_email',
        ]);

        abort(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Dostawca logowania nie potwierdził tego adresu e-mail. Potwierdź adres u dostawcy albo zaloguj się hasłem.'
        );
    }

    /**
     * An account already tied to a different id at this provider is never signed
     * into. There is no legitimate way to reach this: a provider's sub is stable for
     * the account's life, so "same address, different sub" means a second, foreign
     * account at that provider claiming the address.
     *
     * The old guard only declined to overwrite the column, which let the foreign sub
     * sign in anyway and leave no trace of it. Refusing is the point; not overwriting
     * was never the protection it looked like.
     *
     * @param  array{sub: string, email: ?string, email_verified: bool, name: ?string}  $payload
     */
    private function refuseForeignProviderId(?User $user, array $payload, string $providerColumn): void
    {
        // Non-null here is necessarily a DIFFERENT sub: had it matched, the lookup by
        // provider id would have found this very account and we would never be here.
        if ($user === null || $user->{$providerColumn} === null) {
            return;
        }

        Log::warning('Social sign-in refused: account is linked to another provider id.', [
            'provider' => $this->providerName($providerColumn),
            'sub' => $payload['sub'],
            'email' => $payload['email'],
            'user_id' => $user->id,
            'reason' => 'provider_id_mismatch',
        ]);

        abort(
            Response::HTTP_CONFLICT,
            "To konto jest już połączone z innym kontem {$this->providerName($providerColumn)}."
        );
    }

    private function providerName(string $providerColumn): string
    {
        return $providerColumn === 'apple_id' ? 'Apple' : 'Google';
    }

    /**
     * Derive a unique, schema-valid nickname from the email local part.
     */
    private function generateNickname(string $email): string
    {
        $base = preg_replace('/[^a-z0-9_]/', '', Str::lower(Str::before($email, '@'))) ?? '';

        if (strlen($base) < 3) {
            $base = 'user';
        }

        $base = substr($base, 0, 24);
        $candidate = $base;

        while (in_array($candidate, ValidNickname::BLACKLIST, true)
            || User::where('nickname', $candidate)->exists()) {
            $candidate = substr($base, 0, 19).'_'.Str::lower(Str::random(5));
        }

        return $candidate;
    }
}
