<?php

use App\Modules\Rewards\Actions\GrantCreditsAction;
use App\Modules\Rewards\Models\CoupleReward;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 | The database itself refuses a negative balance, whoever writes it. The guard
 | behind the conditional decrement in SpendCreditsAction, for any path that
 | bypasses it.
 */

test('the database refuses a negative balance', function (): void {
    $coupleId = createUserWithCouple()->active_couple_id;
    GrantCreditsAction::run($coupleId, 1);

    expect(fn () => CoupleReward::query()->where('couple_id', $coupleId)->update(['credits' => -1]))
        ->toThrow(QueryException::class, 'couple_rewards_credits_non_negative');
});

test('the constraint is validated, not just declared', function (): void {
    $validated = DB::selectOne(
        "SELECT convalidated FROM pg_constraint WHERE conname = 'couple_rewards_credits_non_negative'"
    );

    expect($validated?->convalidated)->toBeTrue();
});

test('a balance of exactly zero is allowed', function (): void {
    $coupleId = createUserWithCouple()->active_couple_id;
    GrantCreditsAction::run($coupleId, 1);

    CoupleReward::query()->where('couple_id', $coupleId)->decrement('credits', 1);

    expect(creditsOfCouple($coupleId))->toBe(0);
});
