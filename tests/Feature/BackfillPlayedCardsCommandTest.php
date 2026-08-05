<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Models\Couple;
use App\Modules\Memories\Models\Memory;
use App\Modules\Progress\Actions\CheckMilestonesAction;
use App\Modules\Progress\Models\ProgressMilestone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Youandme\Auth\Models\User;

/*
 | progress:backfill-played — the one-off repair for couples whose answers predate
 | the played set (P10 moved the progress map onto it).
 |
 | History is built with the factory rather than through the endpoints on purpose:
 | the factory marks nothing played, which is exactly the situation being
 | repaired — memories on disk, an empty set, and a map that would otherwise read
 | as untouched.
 */

function unlockedCountOf(int $coupleId): int
{
    return DB::table('couple_milestone_unlocks')->where('couple_id', $coupleId)->count();
}

function playedCardsOf(int $coupleId): int
{
    return DB::table('couple_question_seen')->where('couple_id', $coupleId)->count();
}

/** Memories with no trace in the played set, one per distinct question. */
function giveHistory(User $user, int $cards, string $origin = 'session'): void
{
    foreach (range(1, $cards) as $ignored) {
        Memory::factory()->create([
            'user_id' => $user->id,
            'couple_id' => $user->active_couple_id,
            'question_id' => Question::factory()->create()->id,
            'origin' => $origin,
        ]);
    }
}

test('a couple with history gets its played set and every milestone it had earned', function (): void {
    ProgressMilestone::factory()->at(1)->create();
    ProgressMilestone::factory()->at(3)->create();
    ProgressMilestone::factory()->at(10)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    giveHistory($user, 4);

    // Before: answers on disk, nothing played, map untouched.
    expect(playedCardsOf($coupleId))->toBe(0)
        ->and(unlockedCountOf($coupleId))->toBe(0);

    $this->artisan('progress:backfill-played')->assertSuccessful();

    expect(playedCardsOf($coupleId))->toBe(4)
        // Both thresholds at or below 4, and nothing above it.
        ->and(unlockedCountOf($coupleId))->toBe(2);
});

test('locally played history counts as well', function (): void {
    ProgressMilestone::factory()->at(2)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    giveHistory($user, 1);
    giveHistory($user, 1, origin: 'local_game');

    $this->artisan('progress:backfill-played')->assertSuccessful();

    expect(playedCardsOf($coupleId))->toBe(2)
        ->and(unlockedCountOf($coupleId))->toBe(1);
});

test('daily answers stay out of the played set', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    giveHistory($user, 3, origin: 'daily');

    $this->artisan('progress:backfill-played')->assertSuccessful();

    // The same rule the listener applies live: the map counts the question game,
    // the daily card has the streak.
    expect(playedCardsOf($coupleId))->toBe(0)
        ->and(unlockedCountOf($coupleId))->toBe(0);
});

test('a card is stamped with the day it was actually played', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    $question = Question::factory()->create();

    Memory::factory()->create([
        'user_id' => $user->id,
        'couple_id' => $coupleId,
        'question_id' => $question->id,
        'origin' => 'session',
        'answered_at' => now()->subMonths(6),
    ]);

    $this->artisan('progress:backfill-played')->assertSuccessful();

    $seenAt = DB::table('couple_question_seen')
        ->where('couple_id', $coupleId)
        ->where('question_id', $question->id)
        ->value('seen_at');

    // seen_at is a historical fact, not the moment of repair — the DESC index on
    // this table orders by it.
    expect(CarbonImmutable::parse($seenAt)->diffInDays(now()))->toBeGreaterThan(150);
});

test('a question answered twice is one played card', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    $question = Question::factory()->create();

    foreach ([now()->subYear(), now()] as $answeredAt) {
        Memory::factory()->create([
            'user_id' => $user->id,
            'couple_id' => $coupleId,
            'question_id' => $question->id,
            'origin' => 'session',
            'answered_at' => $answeredAt,
        ]);
    }

    $this->artisan('progress:backfill-played')->assertSuccessful();

    expect(playedCardsOf($coupleId))->toBe(1);
});

test('running it again changes nothing', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    giveHistory($user, 2);

    $this->artisan('progress:backfill-played')->assertSuccessful();
    $this->artisan('progress:backfill-played')->assertSuccessful();

    expect(playedCardsOf($coupleId))->toBe(2)
        ->and(unlockedCountOf($coupleId))->toBe(1);
});

test('cards already played keep the timestamp they had', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $question = Question::factory()->create();

    $couple->seenQuestions()->attach($question->id, ['seen_at' => now()->subDays(3)]);

    Memory::factory()->create([
        'user_id' => $user->id,
        'couple_id' => $couple->id,
        'question_id' => $question->id,
        'origin' => 'session',
        'answered_at' => now()->subYear(),
    ]);

    $this->artisan('progress:backfill-played')->assertSuccessful();

    $seenAt = DB::table('couple_question_seen')
        ->where('couple_id', $couple->id)
        ->where('question_id', $question->id)
        ->value('seen_at');

    // ON CONFLICT DO NOTHING: the repair never overwrites what the live path
    // already recorded.
    expect(playedCardsOf($couple->id))->toBe(1)
        ->and(CarbonImmutable::parse($seenAt)->diffInDays(now()))->toBeLessThan(10);
});

test('a couple that has played nothing gets nothing', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $user = createUserWithCouple();

    $this->artisan('progress:backfill-played')->assertSuccessful();

    expect(unlockedCountOf(activeCoupleOf($user)->id))->toBe(0);
});

test('milestones already recorded are left alone and the rest are filled in', function (): void {
    ProgressMilestone::factory()->at(1)->create();
    ProgressMilestone::factory()->at(5)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    giveHistory($user, 6);

    // Partial state: one stage recorded, one missing — what a couple looks like
    // if they played once after the switch but have older history.
    CheckMilestonesAction::run($coupleId, 1);
    expect(unlockedCountOf($coupleId))->toBe(1);

    $this->artisan('progress:backfill-played')->assertSuccessful();

    expect(unlockedCountOf($coupleId))->toBe(2);
});

test('each couple is backfilled against its own history', function (): void {
    ProgressMilestone::factory()->at(1)->create();
    ProgressMilestone::factory()->at(5)->create();

    $busy = createUserWithCouple();
    $quiet = createUserWithCouple();
    giveHistory($busy, 5);
    giveHistory($quiet, 1);

    $this->artisan('progress:backfill-played')->assertSuccessful();

    expect(unlockedCountOf(activeCoupleOf($busy)->id))->toBe(2)
        ->and(unlockedCountOf(activeCoupleOf($quiet)->id))->toBe(1);
});

test('deleted memories still count towards the backfill', function (): void {
    ProgressMilestone::factory()->at(2)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    giveHistory($user, 2);
    Memory::query()->where('couple_id', $coupleId)->first()->delete();

    // Deleting a memory removes it from the couple's history; it does not un-play
    // the card, so a couple cannot lose a backfilled milestone by tidying up.
    $this->artisan('progress:backfill-played')->assertSuccessful();

    expect(playedCardsOf($coupleId))->toBe(2)
        ->and(unlockedCountOf($coupleId))->toBe(1);
});

test('a backfilled couple does not lose the map they already had', function (): void {
    ProgressMilestone::factory()->at(2)->create();

    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);

    // What the switch itself leaves behind: milestones earned under the old
    // counter, recorded, with a played set that knows nothing about them.
    CheckMilestonesAction::run($couple->id, 5);
    giveHistory($user, 2);
    expect(unlockedCountOf($couple->id))->toBe(1);

    $this->artisan('progress:backfill-played')->assertSuccessful();

    // The register is monotonic and the backfill only adds — nothing a couple
    // held before the switch can be taken away by it.
    expect(unlockedCountOf($couple->id))->toBe(1)
        ->and(playedCardsOf($couple->id))->toBe(2);
});

test('the command reports what it did', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $user = createUserWithCouple();
    giveHistory($user, 1);

    $this->artisan('progress:backfill-played')
        ->expectsOutputToContain('Checked 1 couples, recorded 1 played cards and 1 milestones.')
        ->assertSuccessful();
});

test('every couple is walked, not just the first page', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $users = collect(range(1, 3))->map(fn (): User => createUserWithCouple());
    $users->each(fn (User $user) => giveHistory($user, 1));

    $this->artisan('progress:backfill-played')->assertSuccessful();

    expect(Couple::query()->count())->toBe(3);
    $users->each(fn (User $user) => expect(unlockedCountOf((int) $user->active_couple_id))->toBe(1));
});
