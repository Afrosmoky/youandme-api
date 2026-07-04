<?php

namespace Youandme\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Youandme\Auth\Actions\RequestPasswordResetAction;
use Youandme\Auth\Actions\ResetPasswordAction;
use Youandme\Auth\Http\Requests\ForgotPasswordRequest;
use Youandme\Auth\Http\Requests\ResetPasswordRequest;

final class PasswordResetController
{
    /**
     * Email a password reset link. Always 200 regardless of whether the address
     * exists, to avoid leaking which emails are registered.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $status = RequestPasswordResetAction::run($request->string('email')->toString());

        return response()->json(['status' => __($status)]);
    }

    /**
     * Reset the password for a valid token. Invalid/expired tokens yield a 422.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = ResetPasswordAction::run(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('token')->toString(),
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['status' => __($status)]);
    }
}
