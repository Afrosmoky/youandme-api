<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\GameSession;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * @return array{couple_id: int, question_id: int}
 */
function likeRow(Couple $couple, Question $question): ?array
{
    /** @var object{couple_id: int, question_id: int}|null $row */
    $row = DB::table('couple_question_likes')
        ->where('couple_id', $couple->id)
        ->where('question_id', $question->id)
        ->first();

    return $row === null ? null : (array) $row;
}

test('POST like records the like and returns liked:true', function (): void {
    $user = createUserWithCouple();
    $question = Question::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/questions/{$question->ulid}/like")
        ->assertOk()
        ->assertExactJson(['liked' => true]);

    expect(likeRow(activeCoupleOf($user), $question))->not->toBeNull();
});

test('a double POST like stays a single row (idempotent, no PK error)', function (): void {
    $user = createUserWithCouple();
    $question = Question::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/questions/{$question->ulid}/like")->assertOk();
    $this->postJson("/api/v1/questions/{$question->ulid}/like")->assertOk()->assertExactJson(['liked' => true]);

    $count = DB::table('couple_question_likes')
        ->where('couple_id', activeCoupleOf($user)->id)
        ->where('question_id', $question->id)
        ->count();
    expect($count)->toBe(1);
});

test('DELETE like removes the row and returns liked:false', function (): void {
    $user = createUserWithCouple();
    $question = Question::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/questions/{$question->ulid}/like")->assertOk();

    $this->deleteJson("/api/v1/questions/{$question->ulid}/like")
        ->assertOk()
        ->assertExactJson(['liked' => false]);

    expect(likeRow(activeCoupleOf($user), $question))->toBeNull();
});

test('DELETE like when not liked is a no-op returning liked:false', function (): void {
    $user = createUserWithCouple();
    $question = Question::factory()->create();
    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/questions/{$question->ulid}/like")
        ->assertOk()
        ->assertExactJson(['liked' => false]);

    expect(likeRow(activeCoupleOf($user), $question))->toBeNull();
});

test('liking a non-existent question ulid returns 404', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/questions/01JZZZZZZZZZZZZZZZZZZZZZZZ/like')->assertNotFound();
    $this->deleteJson('/api/v1/questions/01JZZZZZZZZZZZZZZZZZZZZZZZ/like')->assertNotFound();
});

test('like endpoints require authentication', function (): void {
    $question = Question::factory()->create();

    $this->postJson("/api/v1/questions/{$question->ulid}/like")->assertUnauthorized();
    $this->deleteJson("/api/v1/questions/{$question->ulid}/like")->assertUnauthorized();
});

test('liked reflects in GET /questions/next — true when liked, false otherwise', function (): void {
    $user = createUserWithCouple();
    $question = Question::factory()->create();
    GameSession::factory()->for(activeCoupleOf($user))->create([
        'state' => ['remaining_ids' => [$question->id], 'current_index' => 0, 'draft_answer' => ''],
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/questions/next')->assertOk()->assertJsonPath('question.liked', false);

    $this->postJson("/api/v1/questions/{$question->ulid}/like")->assertOk();

    $this->getJson('/api/v1/questions/next')->assertOk()->assertJsonPath('question.liked', true);
});

test('liked reflects in GET /daily-card — true when liked, false otherwise', function (): void {
    $questions = Question::factory()->count(20)->create(['type' => 'daily', 'category_id' => null]);
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $servedUlid = $this->getJson('/api/v1/daily-card')
        ->assertOk()
        ->assertJsonPath('liked', false)
        ->json('question.ulid');

    $this->postJson("/api/v1/questions/{$servedUlid}/like")->assertOk();

    $this->getJson('/api/v1/daily-card')->assertOk()->assertJsonPath('liked', true);
});

test('a like does not touch seen, sessions, or streak (disjoint loops)', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $question = Question::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/questions/{$question->ulid}/like")->assertOk();

    // couple_question_seen untouched — a liked question is not marked seen.
    expect(DB::table('couple_question_seen')->where('couple_id', $couple->id)->count())->toBe(0);
    // No session created.
    expect($couple->gameSessions()->count())->toBe(0);
    // Streak columns untouched.
    $couple->refresh();
    expect($couple->streak_current)->toBe(0)
        ->and($couple->streak_longest)->toBe(0)
        ->and($couple->last_daily_answered_on)->toBeNull();
});

test('one couple\'s like is not visible to another couple (per-couple)', function (): void {
    $userA = createUserWithCouple();
    $userB = createUserWithCouple();
    $question = Question::factory()->create();

    Sanctum::actingAs($userA);
    $this->postJson("/api/v1/questions/{$question->ulid}/like")->assertOk();

    expect(likeRow(activeCoupleOf($userA), $question))->not->toBeNull()
        ->and(likeRow(activeCoupleOf($userB), $question))->toBeNull();

    // And couple B sees liked:false when served the same question.
    Sanctum::actingAs($userB);
    GameSession::factory()->for(activeCoupleOf($userB))->create([
        'state' => ['remaining_ids' => [$question->id], 'current_index' => 0, 'draft_answer' => ''],
    ]);
    $this->getJson('/api/v1/questions/next')->assertOk()->assertJsonPath('question.liked', false);
});

test('liking a locked card the couple has not unlocked is refused', function (): void {
    $user = createUserWithCouple();
    $locked = Question::factory()->locked()->create();
    Sanctum::actingAs($user);

    // A client can legitimately learn a locked card's ulid from GET /deck (the
    // shop lists them without their body). Without this guard that ulid would be
    // a way to put paid content on the liked list and read it there.
    $this->postJson("/api/v1/questions/{$locked->ulid}/like")
        ->assertStatus(422)
        ->assertJsonPath('errors.question_ulid.0', 'Karta jest niedostępna dla tej pary.');

    expect(likeRow(activeCoupleOf($user), $locked))->toBeNull();
});

test('liking a locked card the couple unlocked works', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $locked = Question::factory()->locked()->create();
    $couple->unlockedQuestions()->attach($locked->id, ['unlocked_at' => now(), 'source' => 'credits']);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/questions/{$locked->ulid}/like")
        ->assertOk()
        ->assertExactJson(['liked' => true]);

    expect(likeRow($couple, $locked))->not->toBeNull();
});

test('unliking a locked card is never blocked', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $locked = Question::factory()->locked()->create();
    $couple->likedQuestions()->attach($locked->id, ['liked_at' => now()]);
    Sanctum::actingAs($user);

    // Removing a heart takes nothing away from the couple, so the entitlement
    // guard has no business standing in front of it — otherwise a like made
    // before the door closed could never be cleaned up.
    $this->deleteJson("/api/v1/questions/{$locked->ulid}/like")
        ->assertOk()
        ->assertExactJson(['liked' => false]);

    expect(likeRow($couple, $locked))->toBeNull();
});
