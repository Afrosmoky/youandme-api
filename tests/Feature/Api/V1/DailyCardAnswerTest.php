<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Catalog\Queries\GetSessionQuestionPoolQuery;
use App\Modules\Game\Models\GameSession;
use App\Modules\Memories\Models\Memory;
use Laravel\Sanctum\Sanctum;

function seedDailyCardDeck(int $count = 20): void
{
    Question::factory()->count($count)->create(['type' => 'daily', 'category_id' => null]);
}

/** Today's card ulid for the acting user (deterministic per couple per day). */
function todaysDailyUlid(object $test): string
{
    return $test->getJson('/api/v1/daily-card')->json('question.ulid');
}

test('answering the daily card returns 201 {memory, couple} with origin daily and no session', function (): void {
    seedDailyCardDeck();
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/daily-card/answer', [
        'question_ulid' => todaysDailyUlid($this),
        'answer_a' => 'Nasza wspólna odpowiedź.',
    ])
        ->assertCreated()
        ->assertJsonPath('memory.origin', 'daily')
        ->assertJsonPath('couple.streak_current', 1);

    $memory = Memory::firstOrFail();
    expect($memory->origin)->toBe('daily')
        ->and($memory->game_session_id)->toBeNull();

    $couple = activeCoupleOf($user)->fresh();
    expect($couple->streak_current)->toBe(1)
        ->and($couple->last_daily_answered_on->toDateString())->toBe(now($user->timezone)->toDateString());
    expect($response->json('memory.ulid'))->not->toBeNull();
});

test('answering twice on the same day → 409', function (): void {
    seedDailyCardDeck();
    $user = createUserWithCouple();
    Sanctum::actingAs($user);
    $ulid = todaysDailyUlid($this);

    $this->postJson('/api/v1/daily-card/answer', ['question_ulid' => $ulid, 'answer_a' => 'Pierwsza.'])
        ->assertCreated();

    $this->postJson('/api/v1/daily-card/answer', ['question_ulid' => $ulid, 'answer_a' => 'Druga.'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Karta dnia jest już odpowiedziana.');
});

test('answering with a card that is not today\'s → 409 (race guard)', function (): void {
    seedDailyCardDeck();
    $user = createUserWithCouple();
    Sanctum::actingAs($user);
    $today = todaysDailyUlid($this);

    $other = Question::where('type', 'daily')->where('ulid', '!=', $today)->firstOrFail();

    $this->postJson('/api/v1/daily-card/answer', ['question_ulid' => $other->ulid, 'answer_a' => 'A.'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Pytanie nie pasuje do dzisiejszej karty. Pobierz ponownie /daily-card.');

    expect(Memory::count())->toBe(0);
});

test('a live streak (answered yesterday) advances to 2 on today\'s answer', function (): void {
    seedDailyCardDeck();
    $user = createUserWithCouple();
    activeCoupleOf($user)->forceFill([
        'streak_current' => 1,
        'streak_longest' => 1,
        'last_daily_answered_on' => now($user->timezone)->subDay()->toDateString(),
    ])->save();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/daily-card/answer', ['question_ulid' => todaysDailyUlid($this), 'answer_a' => 'A.'])
        ->assertCreated()
        ->assertJsonPath('couple.streak_current', 2)
        ->assertJsonPath('couple.streak_longest', 2);

    expect(activeCoupleOf($user)->fresh()->streak_current)->toBe(2);
});

test('a broken streak (answered two days ago) resets to 1 and keeps the record', function (): void {
    seedDailyCardDeck();
    $user = createUserWithCouple();
    activeCoupleOf($user)->forceFill([
        'streak_current' => 5,
        'streak_longest' => 9,
        'last_daily_answered_on' => now($user->timezone)->subDays(2)->toDateString(),
    ])->save();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/daily-card/answer', ['question_ulid' => todaysDailyUlid($this), 'answer_a' => 'A.'])
        ->assertCreated()
        ->assertJsonPath('couple.streak_current', 1)
        ->assertJsonPath('couple.streak_longest', 9); // historical record never drops
});

test('the daily pool never overlaps the session pool', function (): void {
    seedDailyCardDeck();
    Question::factory()->count(5)->create(['type' => 'session']);

    $sessionPool = GetSessionQuestionPoolQuery::run([], [], null, null);
    $dailyIds = Question::where('type', 'daily')->pluck('id')->all();

    expect(array_values(array_intersect($sessionPool, $dailyIds)))->toBe([]);
});

test('answering the daily card leaves an active game session completely untouched', function (): void {
    seedDailyCardDeck();
    $sessionQuestions = Question::factory()->count(5)->create(['type' => 'session']);
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    GameSession::factory()->for($couple)->create([
        'state' => [
            'remaining_ids' => $sessionQuestions->pluck('id')->all(),
            'current_index' => 0,
            'draft_answer' => '',
        ],
    ]);
    Sanctum::actingAs($user);

    // Snapshot the session before touching the daily card.
    $before = $this->getJson('/api/v1/questions/next')->assertOk();
    $beforeQuestion = $before->json('question.ulid');
    $beforeIndex = $before->json('session.current_index');
    $beforeDrawn = $before->json('session.cards_drawn_count');

    $this->postJson('/api/v1/daily-card/answer', ['question_ulid' => todaysDailyUlid($this), 'answer_a' => 'A.'])
        ->assertCreated();

    // The session serves exactly the same card, at the same index and draw count.
    $after = $this->getJson('/api/v1/questions/next')->assertOk();
    expect($after->json('question.ulid'))->toBe($beforeQuestion)
        ->and($after->json('session.current_index'))->toBe($beforeIndex)
        ->and($after->json('session.cards_drawn_count'))->toBe($beforeDrawn);

    // The daily answer did not enter couple_question_seen (that pivot is the
    // session loop's, not the daily loop's).
    expect($couple->fresh()->seenQuestions()->count())->toBe(0);
});
