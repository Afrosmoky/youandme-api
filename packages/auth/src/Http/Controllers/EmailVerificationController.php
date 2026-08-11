<?php

namespace Youandme\Auth\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Youandme\Auth\Support\DeepLink;
use Youandme\Auth\Actions\SendVerificationEmailAction;
use Youandme\Auth\Actions\VerifyEmailAction;
use Youandme\Auth\Queries\GetVerificationStatusQuery;

final class EmailVerificationController
{
    /**
     * Handle the signed verification link from the email.
     *
     * Answers with a page, not JSON: this URL is opened by a person in whatever
     * browser their mail client hands them, and the only other thing they would
     * see is a raw {"verified":true}. The page confirms it in words and carries
     * them back into the app.
     */
    public function verify(Request $request, string $id, string $hash): View
    {
        VerifyEmailAction::run($id, $hash);

        return view('auth::handoff.layout', [
            'title' => 'Konto zweryfikowane',
            'body' => 'E-mail potwierdzony. Możecie wrócić do gry.',
            'action' => 'Otwórz aplikację',
            'deepLink' => DeepLink::to('email-verified'),
        ]);
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
