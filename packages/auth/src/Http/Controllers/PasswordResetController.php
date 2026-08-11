<?php

namespace Youandme\Auth\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Youandme\Auth\Actions\RequestPasswordResetAction;
use Youandme\Auth\Actions\ResetPasswordAction;
use Youandme\Auth\Http\Requests\ForgotPasswordRequest;
use Youandme\Auth\Http\Requests\ResetPasswordRequest;
use Youandme\Auth\Support\DeepLink;

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

    /**
     * The page the reset mail links to.
     *
     * The mail cannot link jaity:// - custom schemes are not reliably clickable
     * in mail clients - so it points here and this hands the token to the app.
     * Nothing is verified at this step: the token is checked when the new
     * password is actually submitted to the POST above, so an invalid or expired
     * one fails there, in the app, where it can be said properly.
     */
    public function open(Request $request): View
    {
        return view('auth::handoff.layout', [
            'title' => 'Reset hasła',
            'body' => 'Otwórz aplikację, żeby ustawić nowe hasło.',
            'action' => 'Ustaw nowe hasło',
            'deepLink' => DeepLink::to('reset-password', [
                'token' => (string) $request->query('token', ''),
                'email' => (string) $request->query('email', ''),
            ]),
        ]);
    }
}
