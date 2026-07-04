<?php

namespace Youandme\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Youandme\Auth\Actions\SendVerificationEmailAction;
use Youandme\Auth\Actions\VerifyEmailAction;
use Youandme\Auth\Queries\GetVerificationStatusQuery;

class EmailVerificationController
{
    /**
     * Handle the signed verification link from the email.
     */
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        VerifyEmailAction::run($id, $hash);

        return response()->json(['verified' => true]);
    }

    /**
     * Re-send the verification email to the authenticated user.
     */
    public function notification(Request $request): JsonResponse
    {
        $sent = SendVerificationEmailAction::run($request->user());

        return $sent
            ? response()->json(['sent' => true], Response::HTTP_ACCEPTED)
            : response()->json(['verified' => true]);
    }

    /**
     * Soft verification status used by the mobile app to nudge unverified users.
     */
    public function status(Request $request): JsonResponse
    {
        return response()->json(GetVerificationStatusQuery::run($request->user()));
    }
}
