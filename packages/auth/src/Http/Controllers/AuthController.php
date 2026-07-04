<?php

namespace Youandme\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Youandme\Auth\Actions\LogoutUserAction;

/**
 * Couple-free Auth endpoints only. register + login return a couple and are
 * orchestrated in the app layer (App\Http\Controllers\Api\V1\AuthController).
 */
final class AuthController
{
    public function logout(Request $request): Response
    {
        LogoutUserAction::run($request->user());

        return response()->noContent();
    }
}
