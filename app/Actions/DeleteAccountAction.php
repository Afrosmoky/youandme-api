<?php

namespace App\Actions;

use App\Exceptions\AccountDeletionRefusedException;
use App\Modules\Game\Actions\DeleteCoupleAction;
use App\Modules\Game\Actions\DeleteReferralsForUserAction;
use App\Modules\Game\Actions\LockCouplesOfUserAction;
use App\Modules\Game\Queries\IsUserInSharedCoupleQuery;
use App\Modules\Game\Support\CoupleMembership;
use App\Modules\Memories\Actions\DeleteMemoriesForCoupleAction;
use App\Modules\Premium\Actions\DeleteRedeemedCodesForCoupleAction;
use App\Modules\Progress\Actions\DeleteMilestoneUnlocksForCoupleAction;
use App\Modules\Rewards\Actions\DeleteAdRewardNoncesForUserAction;
use App\Modules\Rewards\Actions\DeleteRewardsForCoupleAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Actions\DeleteUserAction;
use Youandme\Auth\Actions\RevokeAppleTokensAction;
use Youandme\Auth\Actions\SetActiveCoupleForUserAction;
use Youandme\Auth\Models\User;
use Youandme\Notifications\Actions\DeleteDeviceTokensForUserAction;

/**
 * Delete an account and everything the user and their couple left behind — the
 * mirror image of registration, and composed the same way: the app layer runs
 * each module's own deletion in one transaction, because no module may touch
 * another's tables.
 *
 * Immediate and hard, with no grace period: both stores ask for the data to be
 * gone, not hidden.
 *
 * The order is the foreign keys' (none of them cascades):
 * - Auth lets go of the couple first — users.active_couple_id and
 *   couples.user_a_id point at each other;
 * - per couple, Memories before Game (memories.game_session_id), and Progress,
 *   Premium and Rewards before Game drops the couple row;
 * - the per-user rows (referrals, ad nonces, device tokens) next, and the users
 *   row itself last.
 *
 * Apple first, outside the transaction: a network call must not hold the locks,
 * and the order costs nothing if the deletion then fails — Sign in with Apple
 * returns the same sub after a revocation, so the account is still reachable.
 * The refusal of a shared couple is checked before that, so a deletion that will
 * not happen revokes nothing.
 *
 * AccountDeletionCoverageTest fails when a new table referencing a user or a
 * couple is not reached from here.
 *
 * Shared by DELETE /me and the account:delete command (requests by e-mail).
 */
final class DeleteAccountAction
{
    use AsAction;

    public function handle(User $user, ?string $appleAuthorizationCode = null): void
    {
        $userUlid = $user->ulid;

        if (IsUserInSharedCoupleQuery::run($user->id)) {
            throw new AccountDeletionRefusedException;
        }

        // Best-effort: never throws, logs what it skipped.
        RevokeAppleTokensAction::run($user, $appleAuthorizationCode);

        DB::transaction(function () use ($user): void {
            // The laravel-actions static proxy is untyped.
            /** @var list<CoupleMembership> $couples */
            $couples = LockCouplesOfUserAction::run($user->id);

            if (collect($couples)->contains(fn (CoupleMembership $couple): bool => $couple->isShared)) {
                throw new AccountDeletionRefusedException;
            }

            SetActiveCoupleForUserAction::run($user, null);

            foreach ($couples as $couple) {
                DeleteMemoriesForCoupleAction::run($couple->coupleId);
                DeleteMilestoneUnlocksForCoupleAction::run($couple->coupleId);
                DeleteRedeemedCodesForCoupleAction::run($couple->coupleId);
                DeleteRewardsForCoupleAction::run($couple->coupleId);
                DeleteCoupleAction::run($couple->coupleId);
            }

            DeleteReferralsForUserAction::run($user->id);
            DeleteAdRewardNoncesForUserAction::run($user->id);
            DeleteDeviceTokensForUserAction::run($user->ulid);
            DeleteUserAction::run($user);
        });

        // Audit trail for "was my account deleted?": the ulid and the time, never
        // the e-mail — the log outlives the account it describes.
        Log::info('Account deleted', [
            'user_ulid' => $userUlid,
            'deleted_at' => now()->toIso8601ZuluString(),
        ]);
    }
}
