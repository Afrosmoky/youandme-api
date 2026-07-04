<?php

namespace Youandme\Auth\Queries;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;

/**
 * Soft verification status for the mobile app: whether the email is verified
 * and how long the account has existed.
 */
class GetVerificationStatusQuery
{
    use AsAction;

    /**
     * @return array{verified: bool, days_since_registration: int}
     */
    public function handle(User $user): array
    {
        return [
            'verified' => $user->hasVerifiedEmail(),
            'days_since_registration' => (int) $user->created_at->diffInDays(),
        ];
    }
}
