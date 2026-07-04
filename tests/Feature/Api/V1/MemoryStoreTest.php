<?php

use App\Modules\Game\Models\GameSession;
use App\Models\Memory;
use App\Modules\Catalog\Models\Question;
use Youandme\Auth\Models\User;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;

/**
 * Set up a user with an active session of $count questions, acting authenticated.
 * The current card is $questions->first().
 *
 * @return array{0: User, 1: GameSession, 2: Collection<int, Question>}
 */
function memorySession(int $count = 5): array
{
    $user = createUserWithCouple();
    $questions = Question::factory()->count($count)->create();

    $session = GameSession::factory()->for(activeCoupleOf($user))->create([
        'state' => [
            'remaining_ids' => $questions->pluck('id')->all(),
            'current_index' => 0,
            'draft_answer' => '',
        ],
    ]);

    Sanctum::actingAs($user);

    return [$user, $session, $questions];
}

/**
 * @return array<string, mixed>
 */
function memoryPayload(Question $question, string $answerA = 'Odpowiedź.'): array
{
    return [
        'question_ulid' => $question->ulid,
        'answer_a' => $answerA,
        'answered_at' => '2026-06-07T10:00:00Z',
    ];
}

test('store returns 422 when there is no active session', function (): void {
    $user = createUserWithCouple();
    $question = Question::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/memories', memoryPayload($question))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Najpierw rozpocznij sesję.');
});

test('store returns 409 when the question does not match the current card', function (): void {
    [, , $questions] = memorySession();

    // Second card, not the current one.
    $this->postJson('/api/v1/memories', memoryPayload($questions[1]))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Pytanie nie pasuje do aktualnej karty sesji. Pobierz ponownie /questions/next.');
});

test('store links the memory to the active session', function (): void {
    [, $session, $questions] = memorySession();

    $response = $this->postJson('/api/v1/memories', memoryPayload($questions->first()))
        ->assertCreated();

    $memory = Memory::firstOrFail();
    expect($memory->game_session_id)->toBe($session->id);
    expect($response->json('memory.origin'))->toBe('session');
});

test('store inserts the question into couple_question_seen', function (): void {
    [$user, , $questions] = memorySession();
    $current = $questions->first();

    $this->postJson('/api/v1/memories', memoryPayload($current))->assertCreated();

    expect(activeCoupleOf($user)->seenQuestions->contains($current))->toBeTrue();
});

test('store advances the session state', function (): void {
    [, $session, $questions] = memorySession();

    $response = $this->postJson('/api/v1/memories', memoryPayload($questions->first()))
        ->assertCreated()
        ->assertJsonPath('session.current_index', 1)
        ->assertJsonPath('session.cards_saved_count', 1)
        ->assertJsonPath('session.cards_drawn_count', 1);

    $session->refresh();
    expect($session->state['current_index'])->toBe(1);
    expect($session->cards_saved_count)->toBe(1);

    expect($response->json('memory.ulid'))->not->toBeNull();
});

test('store keeps the seen insert idempotent on a replayed card', function (): void {
    [$user, $session, $questions] = memorySession();
    $current = $questions->first();

    $this->postJson('/api/v1/memories', memoryPayload($current))->assertCreated();

    // Rewind the session as if the same card were served again.
    $state = $session->fresh()->state;
    $state['current_index'] = 0;
    $session->update(['state' => $state]);

    $this->postJson('/api/v1/memories', memoryPayload($current))->assertCreated();

    expect(activeCoupleOf($user)->fresh()->seenQuestions()->count())->toBe(1);
    expect(Memory::count())->toBe(2);
});

test('store snapshots both player names', function (): void {
    [$user, , $questions] = memorySession();
    $user->update(['nickname' => 'ola']);
    activeCoupleOf($user)->update(['partner_name_local' => 'Tomek']);

    $this->postJson('/api/v1/memories', memoryPayload($questions->first()))
        ->assertCreated()
        ->assertJsonPath('memory.player_a_name', 'ola')
        ->assertJsonPath('memory.player_b_name', 'Tomek');
});

test('store leaves player_b_name null when there is no partner', function (): void {
    [, , $questions] = memorySession();

    $this->postJson('/api/v1/memories', memoryPayload($questions->first()))
        ->assertCreated()
        ->assertJsonPath('memory.player_b_name', null);
});

test('store persists answer_b when provided', function (): void {
    [, , $questions] = memorySession();

    $this->postJson('/api/v1/memories', [
        'question_ulid' => $questions->first()->ulid,
        'answer_a' => 'Moja odpowiedź.',
        'answer_b' => 'Odpowiedź partnera.',
        'answered_at' => '2026-06-07T10:00:00Z',
    ])
        ->assertCreated()
        ->assertJsonPath('memory.answer_a', 'Moja odpowiedź.')
        ->assertJsonPath('memory.answer_b', 'Odpowiedź partnera.');
});

test('store requires answer_a', function (): void {
    [, , $questions] = memorySession();

    $this->postJson('/api/v1/memories', [
        'question_ulid' => $questions->first()->ulid,
        'answered_at' => '2026-06-07T10:00:00Z',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('answer_a');
});

test('storing a memory requires authentication', function (): void {
    $question = Question::factory()->create();

    $this->postJson('/api/v1/memories', memoryPayload($question))->assertUnauthorized();
});
