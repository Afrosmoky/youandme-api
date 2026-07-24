<?php

namespace App\Http\Middleware;

use App\Modules\Game\Actions\AwardPendingReferrerAction;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Youandme\Auth\Actions\MarkFirstOpenedAction;

/**
 * On a user's first authenticated request, mark the account opened and pay any
 * pending referrer. Runs in the app layer (composition root) so it can wire Auth
 * (the marker) and Game (the referral payout) through their Public APIs — neither
 * module reaches into the other.
 *
 * Runs after $next() so auth:sanctum has resolved the user. Three properties:
 *  - cheap in-memory guard: once first_opened_at is set, no DB work at all;
 *  - mark + award in ONE transaction: if the award fails, the mark rolls back and
 *    the next request retries (self-heal), instead of the bonus being lost;
 *  - best-effort: the award is a side effect, so a failure is reported, never
 *    propagated — the user's request must not turn into a 500.
 */
final class AwardReferralOnFirstOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if ($user !== null && $user->first_opened_at === null) {
            try {
                DB::transaction(function () use ($user): void {
                    if (MarkFirstOpenedAction::run($user)) {
                        AwardPendingReferrerAction::run($user->id);
                    }
                });
            } catch (\Throwable $e) {
                report($e); // rolled back → retried on the next request
            }
        }

        return $response;
    }
}
