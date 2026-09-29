<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Actions\ResetDeckAction;
use App\Modules\Game\Models\Couple;
use Illuminate\Support\Facades\DB;

test('stamps the start of the current deck', function (): void {
    $this->freezeSecond();
    $couple = Couple::factory()->create();

    ResetDeckAction::run($couple);

    expect($couple->fresh()->deck_reset_at->equalTo(now()))->toBeTrue();
});

test('a second reset moves the marker forward', function (): void {
    $couple = Couple::factory()->create();
    ResetDeckAction::run($couple);
    $first = $couple->fresh()->deck_reset_at;

    $this->travel(1)->minutes();
    ResetDeckAction::run($couple);

    expect($couple->fresh()->deck_reset_at->greaterThan($first))->toBeTrue();
});

test('deletes no played card', function (): void {
    $couple = Couple::factory()->create();
    $couple->seenQuestions()->attach(Question::factory()->create()->id, ['seen_at' => now()]);

    ResetDeckAction::run($couple);

    expect(DB::table('couple_question_seen')->where('couple_id', $couple->id)->count())->toBe(1);
});
