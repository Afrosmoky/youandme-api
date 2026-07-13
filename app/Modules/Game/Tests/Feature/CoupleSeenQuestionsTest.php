<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Models\Couple;
use Illuminate\Database\UniqueConstraintViolationException;

test('a couple can mark a question as seen via the pivot', function (): void {
    $couple = Couple::factory()->create();
    $question = Question::factory()->create();

    $couple->seenQuestions()->attach($question->id, ['seen_at' => now()]);

    expect($couple->seenQuestions()->count())->toBe(1);
    expect($couple->seenQuestions->contains($question))->toBeTrue();
    // Re-read from the DB, from the Game side of the boundary — the pivot owns the
    // couple_question_seen relation; Catalog's Question does not point back at Couple.
    expect($couple->fresh()->seenQuestions->contains($question))->toBeTrue();
});

test('the composite primary key prevents duplicate seen rows', function (): void {
    $couple = Couple::factory()->create();
    $question = Question::factory()->create();

    $couple->seenQuestions()->attach($question->id, ['seen_at' => now()]);

    expect(fn () => $couple->seenQuestions()->attach($question->id, ['seen_at' => now()]))
        ->toThrow(UniqueConstraintViolationException::class);
});
