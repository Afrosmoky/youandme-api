<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Game\Actions\UpdateCoupleSettingsAction;
use App\Modules\Game\Data\UpdateCoupleSettingsInput;
use App\Modules\Game\Http\Resources\CoupleResource;
use App\Modules\Game\Models\Couple;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Youandme\Auth\Actions\UpdateProfileAction;
use Youandme\Auth\Http\Requests\UpdateProfileRequest;
use Youandme\Auth\Http\Resources\UserResource;

/**
 * App composition root for GET /me and PATCH /me — both return the couple, and
 * PATCH orchestrates Auth (profile fields) + Game (couple settings) in one
 * transaction. See docs r1-architecture-proposal §3.1.
 */
final class ProfileController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $couple = $user->active_couple_id !== null ? Couple::find($user->active_couple_id) : null;

        return response()->json([
            'user' => new UserResource($user),
            'couple' => $couple ? new CoupleResource($couple) : null,
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $couple = $user->active_couple_id !== null ? Couple::find($user->active_couple_id) : null;

        DB::transaction(function () use ($user, $validated, $couple): void {
            UpdateProfileAction::run($user, $validated);

            if (array_key_exists('partner_name_local', $validated) && $couple !== null) {
                UpdateCoupleSettingsAction::run(
                    $couple,
                    new UpdateCoupleSettingsInput($validated['partner_name_local']),
                );
            }
        });

        return response()->json([
            'user' => new UserResource($user),
            'couple' => $couple ? new CoupleResource($couple) : null,
        ]);
    }
}
