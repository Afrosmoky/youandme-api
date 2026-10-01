<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Actions\UnlockQuestionForCoupleAction;
use App\Modules\Game\Support\UnlockSource;
use App\Modules\Rewards\Actions\GrantCreditsAction;
use App\Modules\Rewards\Models\CoupleReward;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Models\User;

/*
 | POST /questions/unlock — several closed cards for credits in one transaction:
 | a list of ulids, all or nothing, owned and freed cards skipped without charge.
 | The price (1 credit) is CardUnlockPrice; the concurrency guarantees are proven
 | with real parallel processes in tests/Concurrency.
 */

function entitledCardsOf(int $coupleId): int
{
    return DB::table('couple_unlocked_questions')->where('couple_id', $coupleId)->count();
}

test('a couple buys several locked cards in one request', function (): void {
    $category = Category::factory()->create(['slug' => 'randka', 'name' => 'Randka']);
    $cards = Question::factory()->locked()->count(4)->create(['category_id' => $category->id]);

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    GrantCreditsAction::run($coupleId, 5);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => [$cards[2]->ulid, $cards[0]->ulid, $cards[1]->ulid]])
        ->assertOk()
        ->assertExactJsonStructure(['charged', 'credits', 'unlocked', 'skipped', 'locked_remaining', 'deck' => ['locked_total', 'unlocked_count', 'complete', 'cards']])
        ->assertJsonPath('charged', 3)
        ->assertJsonPath('credits', 2)
        // Request order, not id order.
        ->assertJsonPath('unlocked', [$cards[2]->ulid, $cards[0]->ulid, $cards[1]->ulid])
        ->assertJsonPath('skipped', [])
        ->assertJsonPath('locked_remaining', 1)
        ->assertJsonPath('deck.unlocked_count', 3)
        ->assertJsonPath('deck.complete', false);

    expect(creditsOfCouple($coupleId))->toBe(2)
        ->and(DB::table('couple_unlocked_questions')->where('couple_id', $coupleId)->pluck('source')->unique()->all())
        ->toBe(['credits']);
});

test('without enough credits for every new card nothing is charged and nothing is unlocked', function (): void {
    $cards = Question::factory()->locked()->count(5)->create();
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    GrantCreditsAction::run($coupleId, 2);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => $cards->pluck('ulid')->all()])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Macie za mało kart do odblokowania.')
        // Two numbers, each declined on its own.
        ->assertJsonPath('errors.credits.0', 'Odblokowanie wybranych kart kosztuje 5 kart, a macie 2 karty.')
        ->assertJsonPath('credits', 2)
        ->assertJsonPath('required', 5);

    expect(creditsOfCouple($coupleId))->toBe(2)
        ->and(entitledCardsOf($coupleId))->toBe(0);
});

test('the shortfall counts only cards that would be new', function (): void {
    $cards = Question::factory()->locked()->count(3)->create();
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    UnlockQuestionForCoupleAction::run($coupleId, $cards[0]->id, UnlockSource::Credits);
    GrantCreditsAction::run($coupleId, 1);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => $cards->pluck('ulid')->all()])
        ->assertUnprocessable()
        ->assertJsonPath('required', 2)
        ->assertJsonPath('errors.credits.0', 'Odblokowanie wybranych kart kosztuje 2 karty, a macie 1 kartę.');

    // The owned card is still owned, the others did not appear.
    expect(entitledCardsOf($coupleId))->toBe(1)
        ->and(creditsOfCouple($coupleId))->toBe(1);
});

test('owned cards are skipped and only new ones are charged', function (): void {
    $cards = Question::factory()->locked()->count(3)->create();
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    UnlockQuestionForCoupleAction::run($coupleId, $cards[1]->id, UnlockSource::Credits);
    GrantCreditsAction::run($coupleId, 5);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => $cards->pluck('ulid')->all()])
        ->assertOk()
        ->assertJsonPath('charged', 2)
        ->assertJsonPath('credits', 3)
        ->assertJsonPath('unlocked', [$cards[0]->ulid, $cards[2]->ulid])
        ->assertJsonPath('skipped', [['ulid' => $cards[1]->ulid, 'reason' => 'already_unlocked']])
        ->assertJsonPath('locked_remaining', 0);
});

test('retrying the same list after a lost response charges nothing more', function (): void {
    $cards = Question::factory()->locked()->count(2)->create();
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    GrantCreditsAction::run($coupleId, 5);
    Sanctum::actingAs($user);
    $body = ['question_ulids' => $cards->pluck('ulid')->all()];

    $this->postJson('/api/v1/questions/unlock', $body)->assertOk()->assertJsonPath('charged', 2);

    $this->postJson('/api/v1/questions/unlock', $body)
        ->assertOk()
        ->assertJsonPath('charged', 0)
        ->assertJsonPath('credits', 3)
        ->assertJsonPath('unlocked', [])
        ->assertJsonCount(2, 'skipped');

    expect(creditsOfCouple($coupleId))->toBe(3);
});

// Mandatory (not an edge case): the only path where a couple would pay with an
// error for an operation that costs nothing. A couple without a reward account
// row would read as "not enough credits" if the debit ran for 0.
test('a request that charges nothing succeeds for a couple without a reward account', function (): void {
    $locked = Question::factory()->locked()->create();
    $free = Question::factory()->create();
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    // Owned through a promo code: no credit was ever granted, so no account row.
    UnlockQuestionForCoupleAction::run($coupleId, $locked->id, UnlockSource::Premium);
    Sanctum::actingAs($user);

    expect(CoupleReward::query()->where('couple_id', $coupleId)->exists())->toBeFalse();

    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => [$locked->ulid, $free->ulid]])
        ->assertOk()
        ->assertJsonPath('charged', 0)
        ->assertJsonPath('credits', 0)
        ->assertJsonPath('unlocked', [])
        ->assertJsonPath('skipped', [
            ['ulid' => $locked->ulid, 'reason' => 'already_unlocked'],
            ['ulid' => $free->ulid, 'reason' => 'not_locked'],
        ]);

    // Reading did not create the account either (CQS).
    expect(CoupleReward::query()->where('couple_id', $coupleId)->exists())->toBeFalse();
});

test('a card that is no longer locked is skipped without charge', function (): void {
    $locked = Question::factory()->locked()->create();
    $freed = Question::factory()->create();
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    GrantCreditsAction::run($coupleId, 3);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => [$freed->ulid, $locked->ulid]])
        ->assertOk()
        ->assertJsonPath('charged', 1)
        ->assertJsonPath('unlocked', [$locked->ulid])
        ->assertJsonPath('skipped', [['ulid' => $freed->ulid, 'reason' => 'not_locked']]);

    // The free card got no entitlement row it would have been charged for.
    expect(entitledCardsOf($coupleId))->toBe(1)
        ->and(creditsOfCouple($coupleId))->toBe(2);
});

test('an unknown card refuses the whole request before anything is written', function (): void {
    $locked = Question::factory()->locked()->create();
    $daily = Question::factory()->create(['type' => 'daily', 'is_locked' => true]);
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    GrantCreditsAction::run($coupleId, 5);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => [$locked->ulid, '01JAAAAAAAAAAAAAAAAAAAAAAA', $daily->ulid]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['question_ulids.1', 'question_ulids.2']);

    expect(entitledCardsOf($coupleId))->toBe(0)
        ->and(creditsOfCouple($coupleId))->toBe(5);
});

test('duplicates in the list are charged once', function (): void {
    $locked = Question::factory()->locked()->create();
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    GrantCreditsAction::run($coupleId, 5);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => [$locked->ulid, $locked->ulid, $locked->ulid]])
        ->assertOk()
        ->assertJsonPath('charged', 1)
        ->assertJsonPath('unlocked', [$locked->ulid])
        ->assertJsonPath('skipped', []);

    expect(creditsOfCouple($coupleId))->toBe(4);
});

test('the list is validated', function (mixed $ulids, string $field): void {
    Sanctum::actingAs(createUserWithCouple());

    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => $ulids])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'missing' => [null, 'question_ulids'],
    'empty' => [[], 'question_ulids'],
    'not a list' => [['a' => '01JAAAAAAAAAAAAAAAAAAAAAAA'], 'question_ulids'],
    'too many' => [array_fill(0, 51, '01JAAAAAAAAAAAAAAAAAAAAAAA'), 'question_ulids'],
    'not a ulid' => [['nope'], 'question_ulids.0'],
]);

test('fifty cards are accepted in one request', function (): void {
    $cards = Question::factory()->locked()->count(50)->create();
    $user = createUserWithCouple();
    GrantCreditsAction::run(activeCoupleOf($user)->id, 50);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => $cards->pluck('ulid')->all()])
        ->assertOk()
        ->assertJsonPath('charged', 50)
        ->assertJsonPath('credits', 0);
});

test('the couple always comes from the token', function (): void {
    $locked = Question::factory()->locked()->create();
    $other = createUserWithCouple();
    $otherCoupleId = activeCoupleOf($other)->id;
    GrantCreditsAction::run($otherCoupleId, 5);

    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    // couple_id in the body is ignored; the caller's own (empty) balance applies.
    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => [$locked->ulid], 'couple_id' => $otherCoupleId])
        ->assertUnprocessable()
        ->assertJsonPath('required', 1)
        ->assertJsonPath('credits', 0);

    expect(creditsOfCouple($otherCoupleId))->toBe(5)
        ->and(entitledCardsOf($otherCoupleId))->toBe(0);
});

test('a user without a couple gets 404', function (): void {
    $locked = Question::factory()->locked()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => [$locked->ulid]])->assertNotFound();
});

test('the bulk unlock requires a token', function (): void {
    $this->postJson('/api/v1/questions/unlock', ['question_ulids' => []])->assertUnauthorized();
});

test('the throttle is per user, not per IP', function (): void {
    $locked = Question::factory()->locked()->create();
    $first = createUserWithCouple();
    $second = createUserWithCouple();
    $body = ['question_ulids' => [$locked->ulid]];

    // Both users come from the same IP (the test client's 127.0.0.1).
    Sanctum::actingAs($first);
    foreach (range(1, 10) as $_) {
        $this->postJson('/api/v1/questions/unlock', $body)->assertStatus(422);
    }
    $this->postJson('/api/v1/questions/unlock', $body)->assertTooManyRequests();

    auth()->forgetGuards();
    Sanctum::actingAs($second);
    $this->postJson('/api/v1/questions/unlock', $body)->assertStatus(422);
});
