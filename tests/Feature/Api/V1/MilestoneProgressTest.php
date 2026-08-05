<?php

use App\Listeners\CheckMilestonesOnCardsPlayed;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Actions\MarkQuestionsPlayedAction;
use App\Modules\Game\Events\CardsPlayed;
use App\Modules\Game\Models\Couple;
use App\Modules\Progress\Models\ProgressMilestone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

/*
 | The progress wiring end to end: playing cards raises the map.
 |
 | P8 hung this on MemoryCreated, because "played" could only be read as "saved".
 | P10 records the play itself, so the listener moved onto CardsPlayed — which
 | changes three things this file has to prove: a skip counts, a locally reported
 | card counts, and the daily card does not (it has the streak instead).
 */

function milestoneCountOf(int $coupleId): int
{
    return DB::table('couple_milestone_unlocks')->where('couple_id', $coupleId)->count();
}

/** Play cards the way any loop does — through Game's single write path. */
function playCardsFor(int $coupleId, int $count): void
{
    MarkQuestionsPlayedAction::run(
        Couple::findOrFail($coupleId),
        Question::factory()->count($count)->create()->pluck('id')->all(),
    );
}

test('answering a session card still advances the map', function (): void {
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

    // The save marks the card played on its way through Game, so moving the
    // counter off memories did not quietly cost the save its effect on the map.
    expect(milestoneCountOf($coupleId))->toBe(1);
});

test('skipping a card advances the map too', function (): void {
    ProgressMilestone::factory()->at(1)->create();
    Question::factory()->count(3)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    $sessionUlid = $this->postJson('/api/v1/sessions/start')->assertCreated()->json('session.ulid');
    $this->postJson("/api/v1/sessions/{$sessionUlid}/skip-current")->assertOk();

    // New in P10: a skipped card was always played, it just never produced a
    // memory — under the old counter it advanced nothing.
    expect(milestoneCountOf($coupleId))->toBe(1);
});

test('a locally reported card advances the map', function (): void {
    ProgressMilestone::factory()->at(2)->create();
    $questions = Question::factory()->count(2)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/game/local/report', [
        'question_ulids' => $questions->pluck('ulid')->all(),
    ])->assertOk();

    expect(milestoneCountOf($coupleId))->toBe(1);
});

test('the daily card does not advance the map', function (): void {
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

    // Deliberate (P10): the map counts the question game, the daily loop has its
    // own system — the streak. Answering it still writes a memory, it just is not
    // a card played in the deck.
    expect(milestoneCountOf($coupleId))->toBe(0);
});

test('cards accumulate across loops until a threshold falls', function (): void {
    ProgressMilestone::factory()->at(3)->create();
    $reported = Question::factory()->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    playCardsFor($coupleId, 2);
    expect(milestoneCountOf($coupleId))->toBe(0);

    $this->postJson('/api/v1/game/local/report', ['question_ulids' => [$reported->ulid]])->assertOk();

    expect(milestoneCountOf($coupleId))->toBe(1);
});

test('further cards past a reached milestone record nothing new', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $coupleId = activeCoupleOf(createUserWithCouple())->id;

    playCardsFor($coupleId, 1);
    playCardsFor($coupleId, 1);
    playCardsFor($coupleId, 1);

    expect(milestoneCountOf($coupleId))->toBe(1);
});

test('replaying the same card does not advance the map twice', function (): void {
    ProgressMilestone::factory()->at(2)->create();

    $couple = Couple::findOrFail(activeCoupleOf(createUserWithCouple())->id);
    $question = Question::factory()->create();

    MarkQuestionsPlayedAction::run($couple, [$question->id]);
    MarkQuestionsPlayedAction::run($couple, [$question->id]);

    // Set semantics all the way through: the counter cannot be inflated by
    // reporting one card twice.
    expect(milestoneCountOf($couple->id))->toBe(0);
});

test('deleting a memory does not undo progress', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $category = Category::factory()->create(['slug' => 'randka']);
    $question = Question::factory()->create(['category_id' => $category->id]);

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/sessions/start')->assertCreated();
    $memoryUlid = $this->postJson('/api/v1/memories', [
        'question_ulid' => $question->ulid,
        'answer_a' => 'nasza odpowiedź',
        'answered_at' => now()->toIso8601ZuluString(),
    ])->assertCreated()->json('memory.ulid');

    $this->deleteJson("/api/v1/memories/{$memoryUlid}")->assertNoContent();

    // Erasing the memory removes it from the couple's history; it does not
    // un-play the card. Since P10 that is true by construction rather than by
    // remembering to say withTrashed — the played set has nothing to delete.
    expect(milestoneCountOf($coupleId))->toBe(1)
        ->and($this->getJson('/api/v1/progress')->json('total_played'))->toBe(1);
});

test('GET /progress reports exactly what the listener counted', function (): void {
    ProgressMilestone::factory()->at(2)->create();

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    playCardsFor($coupleId, 3);

    $this->getJson('/api/v1/progress')
        ->assertOk()
        ->assertJsonPath('total_played', 3)
        ->assertJsonPath('milestones.0.unlocked', true);

    // The read and the write must never disagree about what "played" means.
    expect(milestoneCountOf($coupleId))->toBe(1);
});

test('one couple cards do not advance another couple map', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $player = createUserWithCouple();
    $stranger = createUserWithCouple();

    playCardsFor(activeCoupleOf($player)->id, 1);

    expect(milestoneCountOf(activeCoupleOf($player)->id))->toBe(1)
        ->and(milestoneCountOf(activeCoupleOf($stranger)->id))->toBe(0);
});

test('a rolled back play leaves no phantom progress', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $coupleId = activeCoupleOf(createUserWithCouple())->id;

    try {
        DB::transaction(function () use ($coupleId): void {
            playCardsFor($coupleId, 1);

            throw new RuntimeException('something later in the write failed');
        });
    } catch (RuntimeException) {
        // expected
    }

    // The event is held until commit, so the listener never even runs for cards
    // that did not land.
    expect(milestoneCountOf($coupleId))->toBe(0)
        ->and(DB::table('couple_question_seen')->count())->toBe(0);
});

test('a failing milestone check is swallowed instead of surfacing', function (): void {
    $coupleId = activeCoupleOf(createUserWithCouple())->id;

    // Progress "down" for real rather than mocked (the Action is final): pulling
    // its tables out exercises the same failure the guard exists for. Driven
    // through the listener directly, because a broken query poisons the
    // surrounding test transaction and would take the assertions with it.
    Schema::drop('couple_milestone_unlocks');
    Schema::drop('progress_milestones');

    $listener = new CheckMilestonesOnCardsPlayed;

    // Gamification is a side effect; it has no right to break the cards the
    // couple just played — so nothing may escape the listener.
    expect(fn () => $listener->handle(new CardsPlayed($coupleId)))->not->toThrow(Throwable::class);
});
