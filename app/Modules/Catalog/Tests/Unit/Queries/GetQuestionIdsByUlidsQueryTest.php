<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Catalog\Queries\GetQuestionIdsByUlidsQuery;

test('resolves a batch of ulids to internal ids', function (): void {
    $questions = Question::factory()->count(3)->create();

    $ids = GetQuestionIdsByUlidsQuery::run($questions->pluck('ulid')->all());

    expect($ids)->toEqualCanonicalizing($questions->pluck('id')->all());
});

test('returns an empty list for an empty input', function (): void {
    Question::factory()->create();

    expect(GetQuestionIdsByUlidsQuery::run([]))->toBe([]);
});

test('skips ulids that do not resolve', function (): void {
    $question = Question::factory()->create();

    $ids = GetQuestionIdsByUlidsQuery::run([$question->ulid, '01JZZZZZZZZZZZZZZZZZZZZZZZ']);

    expect($ids)->toBe([$question->id]);
});

test('skips daily questions — only session cards are playable here', function (): void {
    $session = Question::factory()->create();
    $daily = Question::factory()->create(['type' => 'daily']);

    $ids = GetQuestionIdsByUlidsQuery::run([$session->ulid, $daily->ulid]);

    expect($ids)->toBe([$session->id]);
});

test('skips a locked card when the couple has not unlocked it', function (): void {
    $free = Question::factory()->create();
    $locked = Question::factory()->locked()->create();

    $ids = GetQuestionIdsByUlidsQuery::run([$free->ulid, $locked->ulid]);

    expect($ids)->toBe([$free->id]);
});

test('keeps a locked card that is in the unlocked list', function (): void {
    $locked = Question::factory()->locked()->create();

    $ids = GetQuestionIdsByUlidsQuery::run([$locked->ulid], [$locked->id]);

    expect($ids)->toBe([$locked->id]);
});

test('skips a question in another locale', function (): void {
    $pl = Question::factory()->create();
    $en = Question::factory()->create(['locale' => 'en']);

    $ids = GetQuestionIdsByUlidsQuery::run([$pl->ulid, $en->ulid]);

    expect($ids)->toBe([$pl->id]);
});
