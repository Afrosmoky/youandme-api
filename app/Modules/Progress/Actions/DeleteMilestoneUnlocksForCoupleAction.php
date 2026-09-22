<?php

namespace App\Modules\Progress\Actions;

use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Forget which milestones a couple reached — Progress's part of deleting an
 * account. The milestones themselves are catalogue data and stay.
 */
final class DeleteMilestoneUnlocksForCoupleAction
{
    use AsAction;

    public function handle(int $coupleId): void
    {
        DB::table('couple_milestone_unlocks')->where('couple_id', $coupleId)->delete();
    }
}
