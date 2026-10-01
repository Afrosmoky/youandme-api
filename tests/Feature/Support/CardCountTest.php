<?php

use App\Support\CardCount;

/*
 | Polish declines the noun next to every number on its own. The cases that catch
 | a wrong rule: 1, the 2–4 form, the 5+ form, the 12–14 exception (12 takes "kart"
 | although it ends in 2) and its absence at 22 (back to "karty").
 */

test('a number of cards is declined for that number', function (int $count, string $expected): void {
    expect(CardCount::accusative($count))->toBe($expected);
})->with([
    [0, '0 kart'],
    [1, '1 kartę'],
    [2, '2 karty'],
    [4, '4 karty'],
    [5, '5 kart'],
    [12, '12 kart'],
    [14, '14 kart'],
    [22, '22 karty'],
    [25, '25 kart'],
    [112, '112 kart'],
]);
