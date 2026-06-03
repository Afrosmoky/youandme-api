<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailVerificationController extends Controller
{
    /**
     * Handle the signed verification link from the email. The route is guarded
     * by the `signed` middleware; we additionally check the email hash so the
     * link only works for the matching address.
     */
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return response()->json(['verified' => true]);
    }

    /**
     * Re-send the verification email to the authenticated user.
     */
    public function notification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['verified' => true]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['sent' => true], Response::HTTP_ACCEPTED);
    }

    /**
     * Soft verification status: whether the email is verified and how long the
     * account has existed (the mobile app uses this to nudge unverified users).
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'verified' => $user->hasVerifiedEmail(),
            'days_since_registration' => (int) $user->created_at->diffInDays(),
        ]);
    }
}
