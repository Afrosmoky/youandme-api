<?php

namespace Youandme\Auth\Actions\Concerns;

use App\Modules\Game\Models\Couple;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
 */
trait ResolvesSocialSignIn
{
    protected function signIn(SocialTokenVerifier $verifier, string $idToken, string $providerColumn): AuthResult
    {
        try {
            $payload = $verifier->verify($idToken);
        } catch (SocialTokenException $e) {
            abort(Response::HTTP_UNAUTHORIZED, $e->getMessage());
        }

        $user = User::where($providerColumn, $payload['sub'])->first();

        if ($user === null && $payload['email'] !== null) {
            $user = User::where('email', $payload['email'])->first();
        }

        // Apple may omit the email on repeat sign-ins; for a first sign-in we
        // need it (the users.email column is not nullable).
        if ($user === null && $payload['email'] === null) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Email is required to create an account.');
        }

        $isNew = $user === null;

        // Couple is created synchronously inside the transaction (only for a brand
        // new user), so the response carries it. See CLAUDE.md "Wzorce z P3".
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

                // TODO Etap 4 (Game): zastąpić Game\CreateCoupleForUserAction
                $couple = Couple::create(['user_a_id' => $user->id]);
                $user->active_couple_id = $couple->id;
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

            $user->loadMissing('activeCouple');

            return [$user, $user->createToken('mobile')->plainTextToken];
        });

        if ($isNew) {
            UserRegistered::dispatch(UserRegisteredData::fromModel($user));
        }

        return new AuthResult(UserData::fromModel($user), $token, isNewUser: $isNew);
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
