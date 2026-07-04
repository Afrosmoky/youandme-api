<?php

namespace Youandme\Auth\Http\Controllers;

use App\Modules\Game\Http\Resources\CoupleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Youandme\Auth\Actions\LoginUserAction;
use Youandme\Auth\Actions\LogoutUserAction;
use Youandme\Auth\Actions\RegisterUserAction;
use Youandme\Auth\Data\RegisterUserInput;
use Youandme\Auth\Http\Requests\LoginRequest;
use Youandme\Auth\Http\Requests\RegisterRequest;
use Youandme\Auth\Http\Resources\UserResource;
use Youandme\Auth\Models\User;

/**
 * Thin HTTP adapter over the Auth Actions. Reloads the aggregate to serialize
 * the byte-identical response (couple is a Game concern reached via the
 * temporary R1 bridge until Etap 4). CoupleResource stays in App\ until then.
 */
class AuthController
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = RegisterUserAction::run(RegisterUserInput::from($request->validated()));

        $user = User::where('ulid', $result->user->ulid)->firstOrFail()->loadMissing('activeCouple');

        return response()->json([
            'user' => new UserResource($user),
            'couple' => new CoupleResource($user->activeCouple),
            'token' => $result->token,
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = LoginUserAction::run(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        $user = User::where('ulid', $result->user->ulid)->firstOrFail()->loadMissing('activeCouple');

        return response()->json([
            'user' => new UserResource($user),
            'couple' => $user->activeCouple ? new CoupleResource($user->activeCouple) : null,
            'token' => $result->token,
        ]);
    }

    public function logout(Request $request): Response
    {
        LogoutUserAction::run($request->user());

        return response()->noContent();
    }
}
