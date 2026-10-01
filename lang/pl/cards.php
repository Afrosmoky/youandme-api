<?php

declare(strict_types=1);

/*
 | "N cards" as one phrase, declined for the number in front of it. Read through
 | App\Support\CardCount — one call per number that carries the noun, never
 | trans_choice over a whole sentence: Polish declines the noun separately next to
 | every number, and trans_choice picks one form from one number.
 |
 | Plain forms (no ranges) so Laravel's Polish rule picks them: 1 | 2–4, 22–24, … |
 | 0, 5–21, 25–31, … (12–14 take the third form, 22–24 the second).
 */

return [
    // Accusative: "wybraliście / dostaliście …".
    'accusative' => ':count kartę|:count karty|:count kart',
];
