<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Queries\ListSeenQuestionIdsInCurrentDeckQuery;

test('a couple that never reset sees every card it played', function (): void {
    $couple = Couple::factory()->create();
    $questions = Question::factory()->count(2)->create();
    $couple->seenQuestions()->attach([
        $questions[0]->id => ['seen_at' => now()->subYear()],
        $questions[1]->id => ['seen_at' => now()],
    ]);

    expect(ListSeenQuestionIdsInCurrentDeckQuery::run($couple))
        ->toEqualCanonicalizing($questions->pluck('id')->all());
});

test('after a reset only cards seen since the reset belong to the deck', function (): void {
    $couple = Couple::factory()->create();
    [$before, $after] = Question::factory()->count(2)->create();
    $couple->seenQuestions()->attach([
        $before->id => ['seen_at' => now()->subDay()],
        $after->id => ['seen_at' => now()->addMinute()],
    ]);

    $couple->deck_reset_at = now();
    $couple->save();

    expect(ListSeenQuestionIdsInCurrentDeckQuery::run($couple))->toBe([$after->id]);
});

test('a card seen at the reset instant already belongs to the new deck', function (): void {
    $this->freezeSecond();
    $couple = Couple::factory()->create(['deck_reset_at' => now()]);
    $question = Question::factory()->create();
    $couple->seenQuestions()->attach($question->id, ['seen_at' => now()]);

    // >= rather than >: under a frozen clock the reset and the play share a
    // timestamp, and the card was still played after the couple asked for a
    // fresh deck.
    expect(ListSeenQuestionIdsInCurrentDeckQuery::run($couple))->toBe([$question->id]);
});

test('another couple cards never leak into our deck', function (): void {
    $ours = Couple::factory()->create();
    $theirs = Couple::factory()->create();
    $question = Question::factory()->create();
    $theirs->seenQuestions()->attach($question->id, ['seen_at' => now()]);

    expect(ListSeenQuestionIdsInCurrentDeckQuery::run($ours))->toBe([]);
});
