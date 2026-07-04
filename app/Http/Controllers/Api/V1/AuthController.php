<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Game\Actions\CreateCoupleForUserAction;
use App\Modules\Game\Http\Resources\CoupleResource;
use App\Modules\Game\Models\Couple;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Youandme\Auth\Actions\LoginUserAction;
use Youandme\Auth\Actions\RegisterUserAction;
use Youandme\Auth\Actions\SetActiveCoupleForUserAction;
use Youandme\Auth\Data\RegisterUserInput;
use Youandme\Auth\Http\Requests\LoginRequest;
use Youandme\Auth\Http\Requests\RegisterRequest;
use Youandme\Auth\Http\Resources\UserResource;
use Youandme\Auth\Models\User;

/**
 * App composition root for the couple-returning auth endpoints: orchestrates the
 * pure Auth actions with Game (couple creation) in one transaction and composes
 * the {user, couple, token} response (byte-1:1 with P3). See §3.1.
 */
final class AuthController
{
    public function register(RegisterRequest $request): JsonResponse
    {
        [$user, $couple, $token] = DB::transaction(function () use ($request): array {
            $result = RegisterUserAction::run(RegisterUserInput::from($request->validated()));

            $user = User::where('ulid', $result->user->ulid)->firstOrFail();
            $couple = CreateCoupleForUserAction::run($user->id);
            SetActiveCoupleForUserAction::run($user, $couple->id);

            return [$user, $couple, $result->token];
        });

        return response()->json([
            'user' => new UserResource($user),
            'couple' => new CoupleResource($couple),
            'token' => $token,
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = LoginUserAction::run(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        $user = User::where('ulid', $result->user->ulid)->firstOrFail();
        $couple = $user->active_couple_id !== null ? Couple::find($user->active_couple_id) : null;

        return response()->json([
            'user' => new UserResource($user),
            'couple' => $couple ? new CoupleResource($couple) : null,
            'token' => $result->token,
        ]);
    }
}
