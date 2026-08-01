<?php

use App\Listeners\CheckMilestonesOnMemoryCreated;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use App\Modules\Memories\Actions\SaveMemoryAction;
use App\Modules\Memories\Data\MemoryCreatedData;
use App\Modules\Memories\Data\SaveMemoryInput;
use App\Modules\Memories\Events\MemoryCreated;
use App\Modules\Memories\Models\Memory;
use App\Modules\Progress\Models\ProgressMilestone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Models\User;

/*
 | The P8 wiring end to end: a played card raises the progress map. Both loops
 | count, because both save a memory — that is why the listener hangs on
 | MemoryCreated rather than on the session-only QuestionAnswered.
 */

function milestoneCountOf(int $coupleId): int
{
    return DB::table('couple_milestone_unlocks')->where('couple_id', $coupleId)->count();
}

/** Play one card the way the session flow does, without the session scaffolding. */
function playCard(User $user, int $coupleId, string $origin = 'session'): Memory
{
    return SaveMemoryAction::run(new SaveMemoryInput(
        coupleId: $coupleId,
        questionUlid: Question::factory()->create()->ulid,
        userId: $user->id,
        playerAName: $user->nickname,
        playerBName: 'Partner',
        answerA: 'odpowiedź',
        answerB: null,
        gameSessionId: null,
        origin: $origin,
        answeredAt: now(),
    ));
}

test('answering a session card advances the map', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $category = Category::factory()->create(['slug' => 'randka']);
    $question = Question::factory()->create(['category_id' => $category->id]);

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/sessions/start')->assertCreated();
    $this->postJson('/api/v1/memories', [
        'question_ulid' => $question->ulid,
        'answer_a' => 'nasza odpowiedź',
        'answered_at' => now()->toIso8601ZuluString(),
    ])->assertCreated();

    expect(milestoneCountOf($coupleId))->toBe(1);
});

test('answering the daily card advances the map too', function (): void {
    ProgressMilestone::factory()->at(1)->create();
    Question::factory()->count(3)->create(['type' => 'daily', 'category_id' => null]);

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    $todaysCard = $this->getJson('/api/v1/daily-card')->assertOk()->json('question.ulid');

    $this->postJson('/api/v1/daily-card/answer', [
        'question_ulid' => $todaysCard,
        'answer_a' => 'nasza odpowiedź',
    ])->assertCreated();

    // The daily loop is disjoint from sessions but still a played card.
    expect(milestoneCountOf($coupleId))->toBe(1);
});

test('cards accumulate across both loops until a threshold falls', function (): void {
    ProgressMilestone::factory()->at(3)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    playCard($user, $coupleId);
    playCard($user, $coupleId, origin: 'daily');

    expect(milestoneCountOf($coupleId))->toBe(0);

    playCard($user, $coupleId);

    expect(milestoneCountOf($coupleId))->toBe(1);
});

test('further cards past a reached milestone record nothing new', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    playCard($user, $coupleId);
    playCard($user, $coupleId);
    playCard($user, $coupleId);

    expect(milestoneCountOf($coupleId))->toBe(1);
});

test('a deleted memory does not undo progress', function (): void {
    ProgressMilestone::factory()->at(2)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    $first = playCard($user, $coupleId);
    $first->delete();

    // "Played cards" is a lifetime statistic: erasing the memory removes it from
    // the couple's history, it does not un-play the card. The counter therefore
    // counts trashed rows and stays monotonic, like the register it feeds.
    playCard($user, $coupleId);

    expect(milestoneCountOf($coupleId))->toBe(1);
});

test('one couple cards do not advance another couple map', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $player = createUserWithCouple();
    $stranger = createUserWithCouple();

    playCard($player, activeCoupleOf($player)->id);

    expect(milestoneCountOf(activeCoupleOf($player)->id))->toBe(1)
        ->and(milestoneCountOf(activeCoupleOf($stranger)->id))->toBe(0);
});

test('a rolled back save leaves no phantom progress', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    try {
        DB::transaction(function () use ($user, $coupleId): void {
            playCard($user, $coupleId);

            throw new RuntimeException('something later in the write failed');
        });
    } catch (RuntimeException) {
        // expected
    }

    // The event is held until commit, so the listener never even runs for a save
    // that did not land.
    expect(milestoneCountOf($coupleId))->toBe(0)
        ->and(Memory::withTrashed()->count())->toBe(0);
});

test('a failing milestone check is swallowed instead of surfacing', function (): void {
    $coupleId = activeCoupleOf(createUserWithCouple())->id;

    // Progress "down" for real rather than mocked (the Action is final): pulling
    // its tables out exercises the same failure the guard exists for. Driven
    // through the listener directly, because a broken query poisons the
    // surrounding test transaction and would take the assertions with it.
    Schema::drop('couple_milestone_unlocks');
    Schema::drop('progress_milestones');

    $listener = new CheckMilestonesOnMemoryCreated;
    $event = new MemoryCreated(new MemoryCreatedData(
        memoryUlid: '01JQZZZZZZZZZZZZZZZZZZZZZA',
        coupleId: $coupleId,
        questionUlid: '01JQZZZZZZZZZZZZZZZZZZZZZB',
        answeredAt: now()->toIso8601ZuluString(),
    ));

    // Gamification is a side effect; it has no right to break the answer the
    // couple just wrote — so nothing may escape the listener.
    expect(fn () => $listener->handle($event))->not->toThrow(Throwable::class);
});
