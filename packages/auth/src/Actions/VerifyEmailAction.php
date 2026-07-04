<?php

namespace Youandme\Auth\Actions;

use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Events\EmailVerified;
use Youandme\Auth\Models\User;

final class VerifyEmailAction
{
    use AsAction;

    /**
     * Confirm the signed verification link. The route is already `signed`; we
     * additionally check the email hash so a link only works for its address.
     * Aborts 404 (unknown id) / 403 (hash mismatch).
     */
    public function handle(string $id, string $hash): void
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
            EmailVerified::dispatch($user->ulid);
        }
    }
}
