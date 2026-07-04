<?php

namespace Youandme\Auth\Http\Controllers;

use App\Modules\Game\Http\Resources\CoupleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Youandme\Auth\Actions\ChangePasswordAction;
use Youandme\Auth\Actions\UpdateProfileAction;
use Youandme\Auth\Http\Requests\ChangePasswordRequest;
use Youandme\Auth\Http\Requests\UpdateProfileRequest;
use Youandme\Auth\Http\Resources\UserResource;

class ProfileController
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

    /**
     * Cross-module UX orchestration (Auth + Game) in one DB transaction — see
     * docs r1-architecture-proposal §3.1. In Etap 1 the couple update stays
     * inline; TODO Etap 4 (Game): Game\UpdateCoupleSettingsAction.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated): void {
            UpdateProfileAction::run($user, $validated);

            // TODO Etap 4 (Game): zastąpić Game\UpdateCoupleSettingsAction
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
        ChangePasswordAction::run(
            $request->user(),
            $request->string('current_password')->toString(),
            $request->string('new_password')->toString(),
        );

        return response()->json(['message' => 'Hasło zostało zmienione.']);
    }
}
