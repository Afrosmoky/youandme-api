<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Catalog\Queries\GetSessionQuestionLocksByUlidsQuery;

test('resolves session cards with their id and lock flag, keyed by ulid', function (): void {
    $locked = Question::factory()->locked()->create();
    $free = Question::factory()->create();

    $result = GetSessionQuestionLocksByUlidsQuery::run([$locked->ulid, $free->ulid]);

    expect($result)->toHaveCount(2)
        ->and($result[$locked->ulid]->id)->toBe($locked->id)
        ->and($result[$locked->ulid]->isLocked)->toBeTrue()
        ->and($result[$free->ulid]->isLocked)->toBeFalse();
});

test('leaves out unknown ulids and cards outside the session deck', function (): void {
    $session = Question::factory()->locked()->create();
    $daily = Question::factory()->create(['type' => 'daily', 'is_locked' => true]);
    $english = Question::factory()->locked()->create(['locale' => 'en']);

    $result = GetSessionQuestionLocksByUlidsQuery::run([$session->ulid, $daily->ulid, $english->ulid, '01JAAAAAAAAAAAAAAAAAAAAAAA']);

    expect(array_keys($result))->toBe([$session->ulid]);
});

test('an empty list asks nothing', function (): void {
    expect(GetSessionQuestionLocksByUlidsQuery::run([]))->toBe([]);
});
