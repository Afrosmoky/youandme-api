<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Http\Resources\CoupleResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('activeCouple');

        return response()->json([
            'user' => new UserResource($user),
            'couple' => $user->activeCouple ? new CoupleResource($user->activeCouple) : null,
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated): void {
            $user->fill(Arr::except($validated, ['partner_name_local']));
            $user->save();

            if (array_key_exists('partner_name_local', $validated) && $user->activeCouple) {
                $user->activeCouple->partner_name_local = $validated['partner_name_local'];
                $user->activeCouple->save();
            }
        });

        $user->loadMissing('activeCouple');

        return response()->json([
            'user' => new UserResource($user),
            'couple' => $user->activeCouple ? new CoupleResource($user->activeCouple) : null,
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            return response()->json([
                'message' => 'Obecne hasło jest nieprawidłowe.',
                'errors' => ['current_password' => ['Obecne hasło jest nieprawidłowe.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::transaction(function () use ($request, $user): void {
            // The 'hashed' cast on User::password hashes the plaintext on save —
            // assigning Hash::make() here would double-hash it.
            $user->password = $request->string('new_password')->toString();
            $user->save();

            // Keep the current token so the user stays signed in; revoke the rest.
            $currentTokenId = $request->user()->currentAccessToken()->id;
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        });

        return response()->json(['message' => 'Hasło zostało zmienione.']);
    }
}
