<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Actions\UnlockQuestionForCoupleAction;
use App\Modules\Game\Actions\UnlockQuestionsForCoupleAction;
use App\Modules\Game\Support\UnlockSource;
use Illuminate\Support\Facades\DB;

test('returns only the questions this call inserted, ascending', function (): void {
    $questions = Question::factory()->locked()->count(3)->create();
    $coupleId = createUserWithCouple()->active_couple_id;
    UnlockQuestionForCoupleAction::run($coupleId, $questions[1]->id, UnlockSource::Premium);

    $inserted = UnlockQuestionsForCoupleAction::run(
        $coupleId,
        [$questions[2]->id, $questions[1]->id, $questions[0]->id],
        UnlockSource::Credits,
    );

    expect($inserted)->toBe([$questions[0]->id, $questions[2]->id]);

    // The row that was already there keeps its original source.
    expect(DB::table('couple_unlocked_questions')->where('question_id', $questions[1]->id)->value('source'))
        ->toBe('premium');
});

test('a repeated call inserts nothing', function (): void {
    $questions = Question::factory()->locked()->count(2)->create();
    $coupleId = createUserWithCouple()->active_couple_id;
    $ids = $questions->pluck('id')->all();

    UnlockQuestionsForCoupleAction::run($coupleId, $ids, UnlockSource::Credits);

    expect(UnlockQuestionsForCoupleAction::run($coupleId, $ids, UnlockSource::Credits))->toBe([])
        ->and(DB::table('couple_unlocked_questions')->count())->toBe(2);
});

test('duplicate ids are inserted once', function (): void {
    $question = Question::factory()->locked()->create();
    $coupleId = createUserWithCouple()->active_couple_id;

    expect(UnlockQuestionsForCoupleAction::run($coupleId, [$question->id, $question->id], UnlockSource::Credits))
        ->toBe([$question->id]);
});

test('another couple owning the card does not stop this one', function (): void {
    $question = Question::factory()->locked()->create();
    $first = createUserWithCouple()->active_couple_id;
    $second = createUserWithCouple()->active_couple_id;

    UnlockQuestionsForCoupleAction::run($first, [$question->id], UnlockSource::Credits);

    expect(UnlockQuestionsForCoupleAction::run($second, [$question->id], UnlockSource::Credits))->toBe([$question->id]);
});

test('an empty list writes nothing', function (): void {
    expect(UnlockQuestionsForCoupleAction::run(createUserWithCouple()->active_couple_id, [], UnlockSource::Credits))->toBe([]);
});
