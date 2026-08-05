<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use App\Modules\Memories\Models\Memory;
use App\Modules\Progress\Models\ProgressMilestone;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Models\User;

/*
 | POST /memories/local — an answer written during a local game (P10). No server
 | session exists for local play, so the card is authorised by the deck rule
 | instead, and saving it counts as playing it.
 */

function localSavePayload(Question $question, array $overrides = []): array
{
    return array_merge([
        'question_ulid' => $question->ulid,
        'answer_a' => 'Moja odpowiedź',
        'answer_b' => 'Twoja odpowiedź',
        'player_b_name' => 'Ania',
        'answered_at' => now()->toIso8601ZuluString(),
    ], $overrides);
}

test('a local answer is saved without any session', function (): void {
    $category = Category::factory()->create(['slug' => 'randka', 'name' => 'Randka']);
    $question = Question::factory()->create(['category_id' => $category->id]);
    $user = createUserWithCouple(['nickname' => 'Piotr']);
    Sanctum::actingAs($user);

    // No POST /sessions/start anywhere — that is the point.
    $this->postJson('/api/v1/memories/local', localSavePayload($question))
        ->assertCreated()
        ->assertJsonPath('memory.origin', 'local_game')
        ->assertJsonPath('memory.answer_a', 'Moja odpowiedź')
        ->assertJsonPath('memory.answer_b', 'Twoja odpowiedź')
        ->assertJsonPath('memory.player_a_name', 'Piotr')
        ->assertJsonPath('memory.player_b_name', 'Ania')
        ->assertJsonPath('memory.question.ulid', $question->ulid)
        ->assertJsonPath('memory.question.category.slug', 'randka')
        // Nothing to report back about a session the server does not have.
        ->assertJsonMissingPath('session');

    expect(Memory::query()->where('couple_id', $user->active_couple_id)->count())->toBe(1);
});

test('the second player name is a snapshot, not the couple stored partner', function (): void {
    $question = Question::factory()->create();
    $user = createUserWithCouple();
    activeCoupleOf($user)->update(['partner_name_local' => 'Wiktoria']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/memories/local', localSavePayload($question, ['player_b_name' => 'Ania']))
        ->assertCreated()
        // Whoever actually played is who the card records — the couple's stored
        // partner name is a different fact and stays untouched.
        ->assertJsonPath('memory.player_b_name', 'Ania');

    expect(activeCoupleOf($user)->partner_name_local)->toBe('Wiktoria');
});

test('the saved card counts as played', function (): void {
    ProgressMilestone::factory()->at(1)->create();
    $question = Question::factory()->create();
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/memories/local', localSavePayload($question))->assertCreated();

    expect(activeCoupleOf($user)->seenQuestions()->count())->toBe(1)
        ->and($this->getJson('/api/v1/progress')->json('total_played'))->toBe(1)
        ->and(DB::table('couple_milestone_unlocks')->where('couple_id', $user->active_couple_id)->count())->toBe(1);
});

test('the same card in a later batch report is not counted twice', function (): void {
    $question = Question::factory()->create();
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/memories/local', localSavePayload($question))->assertCreated();

    // What the phone actually does at the end of a session: report everything it
    // dealt, saved or not. The set absorbs the overlap.
    $this->postJson('/api/v1/game/local/report', ['question_ulids' => [$question->ulid]])
        ->assertOk()
        ->assertExactJson(['played_total' => 1, 'newly_played' => 0]);
});

test('a saved card does not come back in a later session', function (): void {
    $questions = Question::factory()->count(2)->create();
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/memories/local', localSavePayload($questions[0]))->assertCreated();

    $this->postJson('/api/v1/sessions/start')->assertCreated()
        ->assertJsonPath('session.remaining_count', 1);

    $this->getJson('/api/v1/questions/next')->assertOk()
        ->assertJsonPath('question.ulid', $questions[1]->ulid);
});

test('answer_b may be left out — writing is optional for the second player', function (): void {
    $question = Question::factory()->create();
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/memories/local', localSavePayload($question, ['answer_b' => null]))
        ->assertCreated()
        ->assertJsonPath('memory.answer_b', null);
});

test('the memory lands in the couple own history', function (): void {
    $question = Question::factory()->create();
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/memories/local', localSavePayload($question))->assertCreated();

    $this->getJson('/api/v1/memories')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.origin', 'local_game');
});

test('the memory belongs to the token holder couple, never to another', function (): void {
    $question = Question::factory()->create();
    $player = createUserWithCouple();
    $stranger = createUserWithCouple();
    Sanctum::actingAs($player);

    // couple_id is not an input; sending one changes nothing.
    $this->postJson('/api/v1/memories/local', localSavePayload($question, [
        'couple_id' => $stranger->active_couple_id,
    ]))->assertCreated();

    expect(Memory::query()->where('couple_id', $player->active_couple_id)->count())->toBe(1)
        ->and(Memory::query()->where('couple_id', $stranger->active_couple_id)->count())->toBe(0);
});

test('a locked card the couple has not unlocked is refused', function (): void {
    $locked = Question::factory()->locked()->create();
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/memories/local', localSavePayload($locked))
        ->assertStatus(422)
        ->assertJsonValidationErrors('question_ulid');

    // Neither half of the write happened.
    expect(Memory::query()->count())->toBe(0)
        ->and(activeCoupleOf($user)->seenQuestions()->count())->toBe(0);
});

test('a locked card the couple unlocked is accepted', function (): void {
    $locked = Question::factory()->locked()->create();
    $user = createUserWithCouple();
    activeCoupleOf($user)->unlockedQuestions()->attach($locked->id, [
        'unlocked_at' => now(),
        'source' => 'credits',
    ]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/memories/local', localSavePayload($locked))->assertCreated();
});

test('a daily question cannot be saved as a local game answer', function (): void {
    $daily = Question::factory()->create(['type' => 'daily']);
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/memories/local', localSavePayload($daily))
        ->assertStatus(422)
        ->assertJsonValidationErrors('question_ulid');
});

test('an unknown question is a validation error', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/memories/local', [
        'question_ulid' => '01JZZZZZZZZZZZZZZZZZZZZZZZ',
        'answer_a' => 'Odpowiedź',
        'player_b_name' => 'Ania',
        'answered_at' => now()->toIso8601ZuluString(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('question_ulid');
});

test('the second player name is required', function (): void {
    $question = Question::factory()->create();
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/memories/local', localSavePayload($question, ['player_b_name' => '']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('player_b_name');
});

test('an over-long second player name is refused, like partner_name_local', function (): void {
    $question = Question::factory()->create();
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/memories/local', localSavePayload($question, [
        'player_b_name' => str_repeat('a', 61),
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('player_b_name');
});

test('an answer is required', function (): void {
    $question = Question::factory()->create();
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/memories/local', localSavePayload($question, ['answer_a' => '']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('answer_a');
});

test('the local save requires authentication', function (): void {
    $question = Question::factory()->create();

    $this->postJson('/api/v1/memories/local', localSavePayload($question))->assertUnauthorized();
});

test('a user without a couple cannot save', function (): void {
    $question = Question::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/memories/local', localSavePayload($question))->assertNotFound();
});

test('a future answered_at is refused, a backdated one is not', function (): void {
    $question = Question::factory()->create();
    $older = Question::factory()->create();
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/memories/local', localSavePayload($question, [
        'answered_at' => now()->addDay()->toIso8601ZuluString(),
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('answered_at');

    // Backdating stays legal — a phone that played offline syncs later, and that
    // is the whole reason the client sends a date at all.
    $this->postJson('/api/v1/memories/local', localSavePayload($older, [
        'answered_at' => now()->subDays(2)->toIso8601ZuluString(),
    ]))->assertCreated();

    $this->postJson('/api/v1/memories/local', localSavePayload($question, [
        'answered_at' => now()->toIso8601ZuluString(),
    ]))->assertCreated();
});

test('a phone whose clock runs a little fast is still allowed to save', function (): void {
    $question = Question::factory()->create();
    Sanctum::actingAs(createUserWithCouple());

    // Inside the drift tolerance — the offline case is exactly where a device
    // clock is least likely to have been corrected recently.
    $this->postJson('/api/v1/memories/local', localSavePayload($question, [
        'answered_at' => now()->addMinutes(2)->toIso8601ZuluString(),
    ]))->assertCreated();
});
