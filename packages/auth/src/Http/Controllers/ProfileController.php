<?php

namespace Youandme\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Youandme\Auth\Actions\ChangePasswordAction;
use Youandme\Auth\Http\Requests\ChangePasswordRequest;

/**
 * Couple-free profile endpoint (change password). GET /me and PATCH /me return /
 * touch the couple and are orchestrated in the app layer
 * (App\Http\Controllers\Api\V1\ProfileController).
 */
class ProfileController
{
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
