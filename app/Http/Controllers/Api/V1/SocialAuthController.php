<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Game\Actions\CreateCoupleForUserAction;
use App\Modules\Game\Http\Resources\CoupleResource;
use App\Modules\Game\Models\Couple;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Youandme\Auth\Actions\SetActiveCoupleForUserAction;
use Youandme\Auth\Actions\SignInWithAppleAction;
use Youandme\Auth\Actions\SignInWithGoogleAction;
use Youandme\Auth\Data\AuthResult;
use Youandme\Auth\Http\Requests\SocialSignInRequest;
use Youandme\Auth\Http\Resources\UserResource;
use Youandme\Auth\Models\User;

/**
 * App composition root for social sign-in: the pure Auth action verifies the
 * token and finds-or-creates the user; the couple (only for a brand-new user) is
 * created here via Game. 201 for a new account, 200 for an existing one.
 */
final class SocialAuthController
{
    public function google(SocialSignInRequest $request): JsonResponse
    {
        return $this->respond(
            SignInWithGoogleAction::run($request->string('id_token')->toString())
        );
    }

    public function apple(SocialSignInRequest $request): JsonResponse
    {
        return $this->respond(
            SignInWithAppleAction::run($request->string('id_token')->toString())
        );
    }

    private function respond(AuthResult $result): JsonResponse
    {
        $user = User::where('ulid', $result->user->ulid)->firstOrFail();

        $couple = DB::transaction(function () use ($result, $user): ?Couple {
            if ($result->isNewUser) {
                $couple = CreateCoupleForUserAction::run($user->id);
                SetActiveCoupleForUserAction::run($user, $couple->id);

                return $couple;
            }

            return $user->active_couple_id !== null ? Couple::find($user->active_couple_id) : null;
        });

        return response()->json([
            'user' => new UserResource($user),
            'couple' => $couple ? new CoupleResource($couple) : null,
            'token' => $result->token,
        ], $result->isNewUser ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
