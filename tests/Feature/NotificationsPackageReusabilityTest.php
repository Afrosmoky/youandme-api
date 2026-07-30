<?php

use Symfony\Component\Finder\Finder;

/**
 * Notifications is meant to be liftable into another project, so it may not learn
 * anything about "Ja i Ty". P7 is the first release that really tests that
 * promise: the package gained a push channel whose only consumer is an ad reward,
 * and the tempting shortcut — "just call Rewards from the listener inside the
 * package" — would have quietly ended its reusability.
 *
 * What the package may know: a user ulid, a device token, a message. Not couples,
 * not credits, not rewards, not the game.
 *
 * Sources only; Tests/ is excluded — the same policy as the Auth and Rewards
 * guards (a package test may legitimately create an Auth user to log in).
 */
test('the notifications package does not know the domain it serves', function (): void {
    $files = Finder::create()->files()
        ->in(base_path('packages/notifications/src'))
        ->name('*.php')
        ->notPath('Tests');

    foreach ($files as $file) {
        $name = $file->getRelativePathname();
        $contents = $file->getContents();

        expect($contents)
            ->not->toContain('App\\Modules', "packages/notifications/src/{$name} imports an app module")
            ->and($contents)->not->toContain('Youandme\\Auth', "packages/notifications/src/{$name} imports the Auth package")
            ->and(strtolower($contents))->not->toContain('couple', "packages/notifications/src/{$name} mentions couples")
            ->and(strtolower($contents))->not->toContain('credit', "packages/notifications/src/{$name} mentions credits")
            ->and(strtolower($contents))->not->toContain('reward', "packages/notifications/src/{$name} mentions rewards");
    }
});
