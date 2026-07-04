<?php

use App\Modules\Game\Models\Couple;
use App\Modules\Catalog\Models\Question;
use Illuminate\Database\UniqueConstraintViolationException;

test('a couple can mark a question as seen via the pivot', function (): void {
    $couple = Couple::factory()->create();
    $question = Question::factory()->create();

    $couple->seenQuestions()->attach($question->id, ['seen_at' => now()]);

    expect($couple->seenQuestions()->count())->toBe(1);
    expect($couple->seenQuestions->contains($question))->toBeTrue();
    expect($question->seenByCouples->contains($couple))->toBeTrue();
});

test('the composite primary key prevents duplicate seen rows', function (): void {
    $couple = Couple::factory()->create();
    $question = Question::factory()->create();

    $couple->seenQuestions()->attach($question->id, ['seen_at' => now()]);

    expect(fn () => $couple->seenQuestions()->attach($question->id, ['seen_at' => now()]))
        ->toThrow(UniqueConstraintViolationException::class);
});
