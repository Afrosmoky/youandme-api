<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SocialSignInRequest;
use App\Http\Resources\CoupleResource;
use App\Http\Resources\UserResource;
use App\Models\Couple;
use App\Models\User;
use App\Rules\ValidNickname;
use App\Support\AppleTokenVerifier;
use App\Support\GoogleTokenVerifier;
use App\Support\SocialTokenException;
use App\Support\SocialTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    public function google(SocialSignInRequest $request, GoogleTokenVerifier $verifier): JsonResponse
    {
        return $this->signIn($verifier, $request->string('id_token')->toString(), 'google_id');
    }

    public function apple(SocialSignInRequest $request, AppleTokenVerifier $verifier): JsonResponse
    {
        return $this->signIn($verifier, $request->string('id_token')->toString(), 'apple_id');
    }

    /**
     * Verify the provider token, then find-or-create the matching user and
     * issue a Sanctum token. 201 for a freshly created account, 200 otherwise.
     */
    private function signIn(SocialTokenVerifier $verifier, string $idToken, string $providerColumn): JsonResponse
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
            UserRegistered::dispatch($user);
        }

        return response()->json([
            'user' => new UserResource($user),
            'couple' => $user->activeCouple ? new CoupleResource($user->activeCouple) : null,
            'token' => $token,
        ], $isNew ? Response::HTTP_CREATED : Response::HTTP_OK);
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
