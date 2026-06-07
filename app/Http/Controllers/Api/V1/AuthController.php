<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\CoupleResource;
use App\Http\Resources\UserResource;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        // Couple is created synchronously here (not via UserRegistered listener)
        // so the response can carry it before the client makes further calls.
        // See CLAUDE.md "Wzorce z P3".
        [$user, $token] = DB::transaction(function () use ($request): array {
            $user = User::create($request->validated());

            $couple = Couple::create(['user_a_id' => $user->id]);
            $user->active_couple_id = $couple->id;
            $user->save();
            $user->load('activeCouple');

            return [$user, $user->createToken('mobile')->plainTextToken];
        });

        $user->sendEmailVerificationNotification();

        UserRegistered::dispatch($user);

        return response()->json([
            'user' => new UserResource($user),
            'couple' => new CoupleResource($user->activeCouple),
            'token' => $token,
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            throw new AuthenticationException;
        }

        $token = $user->createToken('mobile')->plainTextToken;

        $user->loadMissing('activeCouple');

        return response()->json([
            'user' => new UserResource($user),
            'couple' => $user->activeCouple ? new CoupleResource($user->activeCouple) : null,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
