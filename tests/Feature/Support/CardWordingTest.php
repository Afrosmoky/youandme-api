<?php

use Symfony\Component\Finder\Finder;

/*
 | The couple's unit is the card. The mobile app dropped the word "kredyt" from its
 | interface, so any server text that reaches a phone or a web page — exception
 | messages, push copy, mails, translations, Blade views — must not bring it back.
 | "credits" stays as the internal / API vocabulary (English, never shown).
 |
 | Tests are excluded: fixtures there never reach anyone.
 */

test('no user-facing server text says "kredyt"', function (): void {
    $root = dirname(__DIR__, 3);

    $files = Finder::create()
        ->files()
        ->in(["{$root}/app", "{$root}/packages/auth/src", "{$root}/packages/notifications/src", "{$root}/lang", "{$root}/resources/views"])
        ->exclude('Tests')
        ->name(['*.php']);

    $offending = [];

    foreach ($files as $file) {
        if (preg_match('/kredyt/iu', $file->getContents()) === 1) {
            $offending[] = $file->getRelativePathname();
        }
    }

    expect($offending)->toBe([]);
});
