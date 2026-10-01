<?php

namespace App\Support;

/**
 * A number of cards as Polish text, declined for that number ("1 kartę",
 * "2 karty", "5 kart"). Called once per number, so a sentence with two numbers
 * declines each one on its own — trans_choice over the whole sentence picks one
 * form from one number and gets the other wrong.
 *
 * Accusative only, because that is the only case the copy needs; write the
 * sentence around a verb that takes it (macie, kosztuje, dostaliście).
 */
final class CardCount
{
    public static function accusative(int $count): string
    {
        // Explicit locale, as with the push copy: a missing APP_LOCALE must never
        // turn user-facing text into a translation key.
        return trans_choice('cards.accusative', $count, [], 'pl');
    }
}
