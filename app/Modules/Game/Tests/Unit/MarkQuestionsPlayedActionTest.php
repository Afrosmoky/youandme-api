<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Actions\MarkQuestionsPlayedAction;
use App\Modules\Game\Events\CardsPlayed;
use App\Modules\Game\Models\Couple;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

function coupleForPlayedCards(): Couple
{
    return Couple::factory()->create();
}

test('marks the given cards played and reports how many were new', function (): void {
    $couple = coupleForPlayedCards();
    $questions = Question::factory()->count(2)->create();

    $newly = MarkQuestionsPlayedAction::run($couple, $questions->pluck('id')->all());

    expect($newly)->toBe(2)
        ->and(DB::table('couple_question_seen')->where('couple_id', $couple->id)->count())->toBe(2);
});

test('a second run over the same cards adds nothing (set semantics)', function (): void {
    $couple = coupleForPlayedCards();
    $question = Question::factory()->create();

    MarkQuestionsPlayedAction::run($couple, [$question->id]);
    $newly = MarkQuestionsPlayedAction::run($couple, [$question->id]);

    expect($newly)->toBe(0)
        ->and(DB::table('couple_question_seen')->where('couple_id', $couple->id)->count())->toBe(1);
});

test('counts only the cards that were new in a partially overlapping run', function (): void {
    $couple = coupleForPlayedCards();
    $questions = Question::factory()->count(3)->create();

    MarkQuestionsPlayedAction::run($couple, [$questions[0]->id]);
    $newly = MarkQuestionsPlayedAction::run($couple, $questions->pluck('id')->all());

    expect($newly)->toBe(2);
});

test('records seen_at for a newly played card', function (): void {
    $couple = coupleForPlayedCards();
    $question = Question::factory()->create();

    MarkQuestionsPlayedAction::run($couple, [$question->id]);

    $seenAt = DB::table('couple_question_seen')
        ->where('couple_id', $couple->id)
        ->where('question_id', $question->id)
        ->value('seen_at');

    expect($seenAt)->not->toBeNull();
});

test('announces CardsPlayed even when nothing was new (self-healing consumer)', function (): void {
    $couple = coupleForPlayedCards();
    $question = Question::factory()->create();
    MarkQuestionsPlayedAction::run($couple, [$question->id]);

    Event::fake([CardsPlayed::class]);
    MarkQuestionsPlayedAction::run($couple, [$question->id]);

    Event::assertDispatchedTimes(CardsPlayed::class, 1);
});

test('does nothing and stays silent for an empty list', function (): void {
    Event::fake([CardsPlayed::class]);
    $couple = coupleForPlayedCards();

    expect(MarkQuestionsPlayedAction::run($couple, []))->toBe(0);

    Event::assertNotDispatched(CardsPlayed::class);
});
