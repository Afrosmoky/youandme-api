<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Actions\UnlockQuestionForCoupleAction;
use App\Modules\Game\Support\UnlockSource;
use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Models\User;

test('the deck lists the locked cards with their category and ownership', function (): void {
    $category = Category::factory()->create(['slug' => 'randka', 'name' => 'Randka']);
    Question::factory()->create(['category_id' => $category->id]);
    $locked = Question::factory()->locked()->create(['category_id' => $category->id]);

    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/deck')
        ->assertOk()
        ->assertExactJson([
            'locked_total' => 1,
            'unlocked_count' => 0,
            'complete' => false,
            'cards' => [[
                'ulid' => $locked->ulid,
                'category' => ['slug' => 'randka', 'name' => 'Randka'],
                'unlocked' => false,
            ]],
        ]);
});

test('a locked card never exposes its body', function (): void {
    $locked = Question::factory()->locked()->create(['body' => 'Sekretna karta?']);
    Sanctum::actingAs(createUserWithCouple());

    $response = $this->getJson('/api/v1/deck')->assertOk();

    expect($response->json())->not->toContain('Sekretna karta?')
        ->and($response->getContent())->not->toContain($locked->body);
});

test('an unlocked card is marked as owned and counted', function (): void {
    $locked = Question::factory()->locked()->create();
    $user = createUserWithCouple();
    UnlockQuestionForCoupleAction::run(activeCoupleOf($user)->id, $locked->id, UnlockSource::Credits);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/deck')
        ->assertOk()
        ->assertJsonPath('unlocked_count', 1)
        ->assertJsonPath('complete', true)
        ->assertJsonPath('cards.0.unlocked', true);
});

test('another couple sees the same card as still locked', function (): void {
    $locked = Question::factory()->locked()->create();
    $owner = createUserWithCouple();
    UnlockQuestionForCoupleAction::run(activeCoupleOf($owner)->id, $locked->id, UnlockSource::Credits);

    Sanctum::actingAs(createUserWithCouple());

    $this->getJson('/api/v1/deck')
        ->assertOk()
        ->assertJsonPath('unlocked_count', 0)
        ->assertJsonPath('cards.0.unlocked', false);
});

test('an empty closed deck is not complete', function (): void {
    Question::factory()->create();
    Sanctum::actingAs(createUserWithCouple());

    // "Nothing left to buy" must not be claimed when there was nothing on sale —
    // P7 slice 2 switches the ad earner off on this flag.
    $this->getJson('/api/v1/deck')
        ->assertOk()
        ->assertJsonPath('locked_total', 0)
        ->assertJsonPath('complete', false);
});

test('the deck requires a token', function (): void {
    $this->getJson('/api/v1/deck')->assertUnauthorized();
});

test('a user without a couple has no deck', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/deck')->assertNotFound();
});
