<?php

namespace Youandme\Auth\Http\Controllers;

use App\Modules\Game\Http\Resources\CoupleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Youandme\Auth\Actions\SignInWithAppleAction;
use Youandme\Auth\Actions\SignInWithGoogleAction;
use Youandme\Auth\Data\AuthResult;
use Youandme\Auth\Http\Requests\SocialSignInRequest;
use Youandme\Auth\Http\Resources\UserResource;
use Youandme\Auth\Models\User;

class SocialAuthController
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

    /**
     * 201 for a freshly created account, 200 for an existing one.
     */
    private function respond(AuthResult $result): JsonResponse
    {
        $user = User::where('ulid', $result->user->ulid)->firstOrFail()->loadMissing('activeCouple');

        return response()->json([
            'user' => new UserResource($user),
            'couple' => $user->activeCouple ? new CoupleResource($user->activeCouple) : null,
            'token' => $result->token,
        ], $result->isNewUser ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
