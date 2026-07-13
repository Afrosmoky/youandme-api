<?php

use App\Modules\Catalog\Models\Question;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\Sanctum;

/**
 * @return Collection<int, Question>
 */
function seedDailyDeck(int $count = 20): Collection
{
    return Question::factory()->count($count)->create(['type' => 'daily', 'category_id' => null]);
}

test('GET /daily-card returns a daily-type question', function (): void {
    seedDailyDeck();
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/daily-card')
        ->assertOk()
        ->assertJsonStructure([
            'question' => ['ulid', 'body', 'type', 'category', 'tags'],
            'answered_today', 'streak_current', 'streak_longest', 'daily_push_hour',
        ]);

    $question = Question::where('ulid', $response->json('question.ulid'))->firstOrFail();
    expect($question->type)->toBe('daily');
    $response->assertJsonPath('question.type', 'daily')
        ->assertJsonPath('question.category', null)
        ->assertJsonPath('answered_today', false);
});

test('GET /daily-card is deterministic — the same card twice on the same day', function (): void {
    seedDailyDeck();
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $first = $this->getJson('/api/v1/daily-card')->json('question.ulid');
    $second = $this->getJson('/api/v1/daily-card')->json('question.ulid');

    expect($first)->toBe($second);
});

test('GET /daily-card reports effective streak 0 when broken, even with a stale column', function (): void {
    seedDailyDeck();
    $user = createUserWithCouple();
    // Streak columns are not fillable (system-managed) — set them directly.
    activeCoupleOf($user)->forceFill([
        'streak_current' => 5,          // stale — last answer is 3 days old
        'streak_longest' => 9,
        'last_daily_answered_on' => now($user->timezone)->subDays(3)->toDateString(),
    ])->save();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/daily-card')
        ->assertOk()
        ->assertJsonPath('streak_current', 0)   // rule wins over the raw column
        ->assertJsonPath('streak_longest', 9);
});

test('GET /daily-card requires authentication', function (): void {
    seedDailyDeck();

    $this->getJson('/api/v1/daily-card')->assertUnauthorized();
});
