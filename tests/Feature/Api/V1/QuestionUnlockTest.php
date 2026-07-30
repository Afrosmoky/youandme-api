<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Actions\UnlockQuestionForCoupleAction;
use App\Modules\Game\Support\UnlockSource;
use App\Modules\Rewards\Actions\GrantCreditsAction;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Models\User;

/*
 | POST /questions/{ulid}/unlock — the app layer spending a Rewards credit on a
 | Game entitlement in one transaction. The price (1 credit) is the app-layer
 | product constant; if it changes, these expectations change with it.
 */

test('a couple with credits buys a locked card', function (): void {
    $category = Category::factory()->create(['slug' => 'randka', 'name' => 'Randka']);
    $locked = Question::factory()->locked()->create(['category_id' => $category->id]);

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    GrantCreditsAction::run($coupleId, 3);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/questions/{$locked->ulid}/unlock")
        ->assertOk()
        ->assertJsonPath('credits', 2)
        ->assertJsonPath('deck.unlocked_count', 1)
        ->assertJsonPath('deck.complete', true)
        ->assertJsonPath('deck.cards.0.unlocked', true);

    expect(creditsOfCouple($coupleId))->toBe(2)
        ->and(DB::table('couple_unlocked_questions')->where('couple_id', $coupleId)->value('source'))
        ->toBe('credits');
});

test('without enough credits nothing is charged and nothing is unlocked', function (): void {
    $locked = Question::factory()->locked()->create();
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/questions/{$locked->ulid}/unlock")
        ->assertUnprocessable()
        ->assertJsonPath('errors.credits.0', 'Za mało kredytów, aby odblokować tę kartę.');

    // The entitlement write is rolled back with the failed debit — no free card.
    expect(creditsOfCouple($coupleId))->toBe(0)
        ->and(DB::table('couple_unlocked_questions')->count())->toBe(0);
});

test('buying the same card twice is refused and costs one credit in total', function (): void {
    $locked = Question::factory()->locked()->create();
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    GrantCreditsAction::run($coupleId, 2);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/questions/{$locked->ulid}/unlock")->assertOk();
    $this->postJson("/api/v1/questions/{$locked->ulid}/unlock")->assertConflict();

    expect(creditsOfCouple($coupleId))->toBe(1)
        ->and(DB::table('couple_unlocked_questions')->count())->toBe(1);
});

test('a free card is not for sale', function (): void {
    $free = Question::factory()->create();
    $user = createUserWithCouple();
    GrantCreditsAction::run(activeCoupleOf($user)->id, 2);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/questions/{$free->ulid}/unlock")
        ->assertUnprocessable()
        ->assertJsonPath('errors.question.0', 'Ta karta nie jest zamknięta.');

    // The guard runs BEFORE the transaction: no credit leaves the balance and no
    // entitlement is written for something that was never on sale.
    expect(creditsOfCouple(activeCoupleOf($user)->id))->toBe(2)
        ->and(DB::table('couple_unlocked_questions')->count())->toBe(0);
});

test('a card that was freed after being locked stops charging credits', function (): void {
    $question = Question::factory()->locked()->create();
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    GrantCreditsAction::run($coupleId, 2);
    Sanctum::actingAs($user);

    // Content decision reverses (Wiktoria frees a card): the same ulid must go
    // from purchasable to "already yours", with no debit either way.
    $question->update(['is_locked' => false]);

    $this->postJson("/api/v1/questions/{$question->ulid}/unlock")->assertUnprocessable();

    expect(creditsOfCouple($coupleId))->toBe(2)
        ->and(DB::table('couple_unlocked_questions')->count())->toBe(0);
});

test('an unknown question ulid is a 404', function (): void {
    $user = createUserWithCouple();
    GrantCreditsAction::run(activeCoupleOf($user)->id, 2);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/questions/01JZZZZZZZZZZZZZZZZZZZZZZZ/unlock')->assertNotFound();

    expect(creditsOfCouple(activeCoupleOf($user)->id))->toBe(2);
});

test('the unlock lands on the couple from the token, never on another one', function (): void {
    $locked = Question::factory()->locked()->create();

    $buyer = createUserWithCouple();
    $buyerCoupleId = activeCoupleOf($buyer)->id;
    GrantCreditsAction::run($buyerCoupleId, 1);

    $stranger = createUserWithCouple();
    $strangerCoupleId = activeCoupleOf($stranger)->id;
    GrantCreditsAction::run($strangerCoupleId, 1);

    Sanctum::actingAs($buyer);
    $this->postJson("/api/v1/questions/{$locked->ulid}/unlock")->assertOk();

    expect(creditsOfCouple($buyerCoupleId))->toBe(0)
        ->and(creditsOfCouple($strangerCoupleId))->toBe(1)
        ->and(DB::table('couple_unlocked_questions')->where('couple_id', $strangerCoupleId)->count())->toBe(0);
});

test('a card unlocked by a promo code is not charged again as credits', function (): void {
    $locked = Question::factory()->locked()->create();
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    GrantCreditsAction::run($coupleId, 1);
    UnlockQuestionForCoupleAction::run($coupleId, $locked->id, UnlockSource::Premium);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/questions/{$locked->ulid}/unlock")->assertConflict();

    expect(creditsOfCouple($coupleId))->toBe(1);
});

test('unlocking requires a token', function (): void {
    $locked = Question::factory()->locked()->create();

    $this->postJson("/api/v1/questions/{$locked->ulid}/unlock")->assertUnauthorized();
});

test('a user without a couple cannot unlock', function (): void {
    $locked = Question::factory()->locked()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/questions/{$locked->ulid}/unlock")->assertNotFound();
});
