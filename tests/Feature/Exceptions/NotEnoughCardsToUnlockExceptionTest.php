<?php

use App\Exceptions\NotEnoughCardsToUnlockException;
use Illuminate\Http\Request;

/*
 | The bulk-unlock shortfall sentence. Only the selected count carries the noun,
 | so that is where the declension can go wrong: 1 | 2–4 | 5+, with 12 back in the
 | third form and 22 back in the second.
 */

test('the shortfall sentence declines the number of selected cards', function (int $selected, int $affordable, string $expected): void {
    $response = (new NotEnoughCardsToUnlockException(required: $selected, credits: $affordable))
        ->render(Request::create('/'));

    expect($response->getData(true)['errors']['credits'][0])->toBe($expected);
})->with([
    [1, 0, 'Wybraliście 1 kartę, a możecie odblokować 0.'],
    [2, 1, 'Wybraliście 2 karty, a możecie odblokować 1.'],
    [5, 2, 'Wybraliście 5 kart, a możecie odblokować 2.'],
    [12, 4, 'Wybraliście 12 kart, a możecie odblokować 4.'],
    [22, 21, 'Wybraliście 22 karty, a możecie odblokować 21.'],
    [25, 3, 'Wybraliście 25 kart, a możecie odblokować 3.'],
]);

test('the body keeps both raw numbers and the shared message', function (): void {
    $data = (new NotEnoughCardsToUnlockException(required: 5, credits: 2))
        ->render(Request::create('/'))
        ->getData(true);

    expect($data['message'])->toBe('Macie za mało kart do odblokowania.')
        ->and($data['credits'])->toBe(2)
        ->and($data['required'])->toBe(5);
});
