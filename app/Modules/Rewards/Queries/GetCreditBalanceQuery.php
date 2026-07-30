<?php

namespace App\Modules\Rewards\Queries;

use App\Modules\Rewards\Models\CoupleReward;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Just the balance, for callers that need the number and nothing else (the
 * unlock orchestrator answers with the credits left after the debit). No account
 * row means nothing was ever granted — that is 0, not an error, and reading it
 * must not create the row (CQS).
 */
final class GetCreditBalanceQuery
{
    use AsAction;

    public function handle(int $coupleId): int
    {
        return (int) CoupleReward::query()
            ->where('couple_id', $coupleId)
            ->value('credits');
    }
}
