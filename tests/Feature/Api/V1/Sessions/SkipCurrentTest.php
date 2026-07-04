<?php

use App\Models\GameSession;
use App\Modules\Catalog\Models\Question;
use Youandme\Auth\Models\User;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;

/**
 * @return array{0: User, 1: GameSession, 2: Collection<int, Question>}
 */
function startedSession(int $count = 5, int $currentIndex = 0): array
{
    $user = User::factory()->create();
    $questions = Question::factory()->count($count)->create();

    $session = GameSession::factory()->for($user->activeCouple)->create([
        'state' => [
            'remaining_ids' => $questions->pluck('id')->all(),
            'current_index' => $currentIndex,
            'draft_answer' => '',
        ],
    ]);

    return [$user, $session, $questions];
}

test('skip marks the current question as seen and advances the index', function (): void {
    [$user, $session, $questions] = startedSession();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/sessions/{$session->ulid}/skip-current")
        ->assertOk()
        ->assertJsonPath('session.current_index', 1)
        ->assertJsonPath('session.cards_drawn_count', 1);

    $couple = $user->activeCouple->fresh();
    expect($couple->seenQuestions()->count())->toBe(1);
    expect($couple->seenQuestions->contains($questions->first()))->toBeTrue();
});

test('skip returns 422 when there are no questions left', function (): void {
    [$user, $session] = startedSession(count: 5, currentIndex: 5);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/sessions/{$session->ulid}/skip-current")->assertStatus(422);
});

test('skip returns 403 when the session belongs to a different couple', function (): void {
    [, $session] = startedSession();

    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/sessions/{$session->ulid}/skip-current")->assertForbidden();
});

test('skip returns 410 when the session has ended', function (): void {
    [$user, $session] = startedSession();
    $session->update(['ended_at' => now()]);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/sessions/{$session->ulid}/skip-current")->assertStatus(410);
});

test('skip does not duplicate an already-seen entry', function (): void {
    [$user, $session, $questions] = startedSession();
    $user->activeCouple->seenQuestions()->attach($questions->first()->id, ['seen_at' => now()]);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/sessions/{$session->ulid}/skip-current")
        ->assertOk()
        ->assertJsonPath('session.current_index', 1);

    expect($user->activeCouple->fresh()->seenQuestions()->count())->toBe(1);
});

test('skipping requires authentication', function (): void {
    [, $session] = startedSession();

    $this->postJson("/api/v1/sessions/{$session->ulid}/skip-current")->assertUnauthorized();
});
