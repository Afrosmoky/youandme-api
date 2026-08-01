<?php

use Symfony\Component\Finder\Finder;

/**
 * Progress is a LEAF (Progress → ∅), and P8 is exactly the release where that
 * could have slipped: the milestone check needs a card count, and the obvious
 * shortcut — "just COUNT memories from inside Progress" — would have bought an
 * edge Progress→Memories for one query. Instead the total arrives as a value and
 * the module stays independent.
 *
 * Sources only; Tests/ is excluded (a test legitimately builds a couple to have
 * an id) — the same policy as the Rewards, Premium and Auth guards.
 */
test('the Progress module is a leaf — it depends on no other module or package', function (): void {
    $files = Finder::create()->files()->in(app_path('Modules/Progress'))->name('*.php')->notPath('Tests');

    foreach ($files as $file) {
        $name = $file->getRelativePathname();
        $contents = $file->getContents();

        expect($contents)
            ->not->toContain('App\\Modules\\Memories', "app/Modules/Progress/{$name} imports Memories")
            ->and($contents)->not->toContain('App\\Modules\\Game', "app/Modules/Progress/{$name} imports Game")
            ->and($contents)->not->toContain('App\\Modules\\Catalog', "app/Modules/Progress/{$name} imports Catalog")
            ->and($contents)->not->toContain('App\\Modules\\Rewards', "app/Modules/Progress/{$name} imports Rewards")
            ->and($contents)->not->toContain('App\\Modules\\Premium', "app/Modules/Progress/{$name} imports Premium")
            ->and($contents)->not->toContain('Youandme\\', "app/Modules/Progress/{$name} imports a reusable package");
    }
});

test('no other module knows the Progress module', function (): void {
    $files = Finder::create()->files()
        ->in([
            app_path('Modules/Game'),
            app_path('Modules/Catalog'),
            app_path('Modules/Memories'),
            app_path('Modules/Rewards'),
            app_path('Modules/Premium'),
            base_path('packages/auth/src'),
            base_path('packages/notifications/src'),
        ])
        ->name('*.php')
        ->notPath('Tests');

    // Only the app layer may name Progress — the listener is the single bridge,
    // and Memories must not learn that anything reacts to its event.
    foreach ($files as $file) {
        expect($file->getContents())
            ->not->toContain('App\\Modules\\Progress', "{$file->getRelativePathname()} imports the Progress module");
    }
});
