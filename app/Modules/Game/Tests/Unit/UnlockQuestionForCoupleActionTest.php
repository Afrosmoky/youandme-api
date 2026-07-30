<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Actions\UnlockQuestionForCoupleAction;
use App\Modules\Game\Support\UnlockSource;
use Illuminate\Support\Facades\DB;

test('the first unlock writes the entitlement and reports it as new', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    $question = Question::factory()->locked()->create();

    expect(UnlockQuestionForCoupleAction::run($couple->id, $question->id, UnlockSource::Credits))->toBeTrue();

    $row = DB::table('couple_unlocked_questions')
        ->where('couple_id', $couple->id)
        ->where('question_id', $question->id)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->source)->toBe('credits');
});

test('unlocking the same card twice reports false and inserts nothing', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    $question = Question::factory()->locked()->create();

    UnlockQuestionForCoupleAction::run($couple->id, $question->id, UnlockSource::Credits);

    // False is the signal the orchestrator charges on — this is what stops a
    // second credit from being taken for a card the couple already owns.
    expect(UnlockQuestionForCoupleAction::run($couple->id, $question->id, UnlockSource::Premium))->toBeFalse()
        ->and(DB::table('couple_unlocked_questions')->count())->toBe(1);
});

test('the source of the unlock is recorded', function (): void {
    $couple = activeCoupleOf(createUserWithCouple());
    $question = Question::factory()->locked()->create();

    UnlockQuestionForCoupleAction::run($couple->id, $question->id, UnlockSource::Premium);

    expect(DB::table('couple_unlocked_questions')->value('source'))->toBe('premium');
});

test('two couples unlock the same card independently', function (): void {
    $first = activeCoupleOf(createUserWithCouple());
    $second = activeCoupleOf(createUserWithCouple());
    $question = Question::factory()->locked()->create();

    expect(UnlockQuestionForCoupleAction::run($first->id, $question->id, UnlockSource::Credits))->toBeTrue()
        ->and(UnlockQuestionForCoupleAction::run($second->id, $question->id, UnlockSource::Credits))->toBeTrue()
        ->and($first->unlockedQuestions()->count())->toBe(1)
        ->and($second->unlockedQuestions()->count())->toBe(1);
});
