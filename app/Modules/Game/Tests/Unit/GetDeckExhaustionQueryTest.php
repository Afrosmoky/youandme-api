<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Queries\GetDeckExhaustionQuery;
use App\Modules\Game\Support\ExhaustionReason;

/*
 | The reason ladder in isolation: which of the three states a couple is in, and
 | which one wins when more than one could be claimed. The feature tests prove the
 | endpoint carries it; this proves the order.
 */

test('free cards elsewhere beat cards behind the lock', function (): void {
    $asked = Category::factory()->create(['slug' => 'randka']);
    $other = Category::factory()->create(['slug' => 'intymnosc']);
    Question::factory()->create(['category_id' => $other->id]);
    Question::factory()->locked()->create(['category_id' => $asked->id]);

    $exhaustion = GetDeckExhaustionQuery::run([], [], 'randka');

    // Both claims are true; the cheaper one for the couple wins.
    expect($exhaustion->reason)->toBe(ExhaustionReason::OtherCategories)
        ->and($exhaustion->lockedRemaining)->toBe(1);
});

test('with nothing playable anywhere the lock is the answer', function (): void {
    $asked = Category::factory()->create(['slug' => 'randka']);
    $played = Question::factory()->create(['category_id' => $asked->id]);
    Question::factory()->locked()->count(2)->create();

    $exhaustion = GetDeckExhaustionQuery::run([$played->id], [], 'randka');

    expect($exhaustion->reason)->toBe(ExhaustionReason::LockedAvailable)
        ->and($exhaustion->lockedRemaining)->toBe(2);
});

test('an unlocked-but-unplayed card is playable, not funnel material', function (): void {
    $asked = Category::factory()->create(['slug' => 'randka']);
    $played = Question::factory()->create(['category_id' => $asked->id]);
    $bought = Question::factory()->locked()->create();

    $exhaustion = GetDeckExhaustionQuery::run([$played->id], [$bought->id], 'randka');

    // They already paid for it and have not played it: that is a card waiting in
    // another category, and there is nothing left to sell.
    expect($exhaustion->reason)->toBe(ExhaustionReason::OtherCategories)
        ->and($exhaustion->lockedRemaining)->toBe(0);
});

test('nothing anywhere and nothing to buy is complete', function (): void {
    $played = Question::factory()->create();

    $exhaustion = GetDeckExhaustionQuery::run([$played->id], [], null);

    expect($exhaustion->reason)->toBe(ExhaustionReason::Complete)
        ->and($exhaustion->lockedRemaining)->toBe(0);
});

test('mix skips the elsewhere question entirely', function (): void {
    $date = Category::factory()->create(['slug' => 'randka']);
    $other = Category::factory()->create(['slug' => 'intymnosc']);
    $one = Question::factory()->create(['category_id' => $date->id]);
    $two = Question::factory()->create(['category_id' => $other->id]);

    // Deliberately only one of the two marked seen: in a category request this
    // state would answer other_categories. Mix cannot, by definition — it was
    // already dealing from every category.
    $exhaustion = GetDeckExhaustionQuery::run([$one->id, $two->id], [], null);

    expect($exhaustion->reason)->toBe(ExhaustionReason::Complete);
});

test('daily and non-pl cards are outside the reason, as they are outside the pool', function (): void {
    $asked = Category::factory()->create(['slug' => 'randka']);
    $played = Question::factory()->create(['category_id' => $asked->id]);
    Question::factory()->create(['type' => 'daily']);
    Question::factory()->create(['locale' => 'en']);
    Question::factory()->locked()->create(['type' => 'daily']);

    $exhaustion = GetDeckExhaustionQuery::run([$played->id], [], 'randka');

    // One shared builder with the pool is what makes this hold: a card the deck
    // would never deal cannot be a reason the deck is empty.
    expect($exhaustion->reason)->toBe(ExhaustionReason::Complete)
        ->and($exhaustion->lockedRemaining)->toBe(0);
});
